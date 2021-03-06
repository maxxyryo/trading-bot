<?php

namespace App\Blls;

use App\Blls\ExchangeInfoBll;
use App\Jobs\UpdateCandlesticksJob;
use App\Models\Candlesticks1m;
use App\Models\Candlesticks5m;
use Binance\API as BinanceApi;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;

class CandlesticksBll
{
    protected $binanceApi;
    protected $exchangeInfoBll;
    const SYMBOL_CHUNK_SIZE = 65;

    public function __construct(
        BinanceApi $binanceApi
    )
    {
        $this->binanceApi = $binanceApi;
    }

    public function exchangeInfoBll()
    {
        if ($this->exchangeInfoBll)
        {
            return $this->exchangeInfoBll;
        }

        return $this->exchangeInfoBll = resolve(ExchangeInfoBll::class);
    }

    public function fetchApiData($symbol, $interval, $startTime = null, $endTime = null, $skipDateTimeParsing = false)
    {
        if (! $skipDateTimeParsing)
        {
            $startTime = $this->parseDate($startTime, 'from');
            $endTime = $this->parseDate($endTime, 'to');
        }

        // Fetch from API
        logger("Fetching $symbol $interval candlesticks $startTime..$endTime");
        $data = $this->binanceApi->candlesticks($symbol, $interval, $startTime, $endTime);
        $keys = array_keys($data);
        logger(count($keys) . " candlesticks fetched.");

        $enc = json_encode($data);
        $data = json_decode($enc);

        $filename = implode('-', ['candlesticks', $symbol, $interval, 's-' . $startTime, 'e-' . end($keys)]) . '.json';

        #Storage::put($filename, $enc);
        #logger("Saved to $filename");

        return $data;
    }

    public function storeCandlesticks($symbol, $interval, $data)
    {
        $numSaved = 0;

        foreach ($data as $rowData)
        {
            $unixTimestamp = substr($rowData->openTime, 0, -3);

            // Discard anything for the current minute (because the data isn't complete yet)
            if ((int) $unixTimestamp === (int) floor(now()->timestamp/60)*60)
            {
                continue;
            }

            $datetime_ = Carbon::createFromTimestampUTC($unixTimestamp)->toDateTimeString();

            $className = "App\Models\Candlesticks$interval";

            $cstick = $className::firstOrNew([
                'symbol' => $symbol,
                'datetime_' => $datetime_,
            ]);

            $cstick->open = $rowData->open;
            $cstick->high = $rowData->high;
            $cstick->low = $rowData->low;
            $cstick->close = $rowData->close;
            $cstick->volume = $rowData->volume;

            $cstick->save();
            $numSaved++;
        }

        return [$numSaved, $rowData->openTime];
    }

    /**
     * This method processes and massages the input params before passing to the Enqueue method or to the Finish (fetching/storing) method
     *
     * @param string|string[] $symbols String (one symbol) or array of symbol strings. 'ALL' fetches all symbol names from storage.
     * @param $interval e.g. '1m', '5m', etc
     * @param null $startTime
     * @param null $endTime
     * @param bool $skipQueue
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch|int
     * @throws Exception
     * @throws \Throwable
     */
    public function fetchAndStoreCandlesticksStart($symbols, $interval, $startTime = null, $endTime = null, $skipQueue = false)
    {
        $symbols = (array) $symbols;

        if (strtoupper($symbols[0]) === 'ALL')
        {
            $symbols = $this->exchangeInfoBll()->allSymbols();
        }

        $startTime = $this->parseDate($startTime, 'from');
        $endTime = $this->parseDate($endTime, 'to');

        if ($skipQueue) {
            return $this->fetchAndStoreCandlesticksFinish($symbols, $interval, $startTime, $endTime);
        }

        return $this->fetchAndStoreCandlesticksEnqueue($symbols, $interval, $startTime, $endTime, $skipQueue);
    }

    public function fetchAndStoreCandlesticksEnqueue($symbols, $interval, $startTime, $endTime)
    {
        // For queue jobs, put each symbol in its own job for parallelization
        foreach ($symbols as $symbol)
        {
            UpdateCandlesticksJob::dispatch([$symbol], $interval, $startTime, $endTime);
        }

        return true;
    }

    /**
     * Prerequisite: fetchAndStoreCandlesticksStart
     *
     * Fetches the candlestick data from the API and stores it in the db
     *
     * @param $symbols
     * @param $interval
     * @param $startTime
     * @param $endTime
     * @return int Total candlesticks fetched and stored
     */
    public function fetchAndStoreCandlesticksFinish($symbols, $interval, $startTime, $endTime)
    {
        $totalNumStored = 0;

        foreach ($symbols as $symbol)
        {
            $currStartTime = $startTime;

            do
            {
                $data = $this->fetchApiData($symbol, $interval, $currStartTime, $endTime, true);
                list($numStored, $lastRowTime) = $this->storeCandlesticks($symbol, $interval, $data);
                #logger("Stored $numStored candlesticks_$interval: $symbol");
                $totalNumStored += $numStored;

                $lastStartTime = $currStartTime;
                $currStartTime = $lastRowTime;
            }
            while ($lastRowTime > $lastStartTime && (!$endTime || $lastRowTime < $endTime));
        }

        #logger("Stored $totalNumStored candlesticks_$interval");

        return $totalNumStored;
    }

    /**
     * Accepts any recognizable date string/int within the past year, and returns a unix timestamp in microseconds.
     *
     * @param string|int $value A string or int in any date format
     * @param string $optionName e.g. 'start' or 'end', used for exception message if $value is invalid
     *
     * @return string|null Unix timestamp (string) in microseconds (or null if $value is null)
     * @throws Exception if $value is not a valid date within the past year
     */
    public function parseDate($value, $optionName)
    {
        if ($value === null)
        {
            return $value;
        }

        $valueLen = strlen($value);

        // For some reason, Carbon can't ::parse() unix timestamps, so do it manually
        // Also, account/allow for the timestamp being in microseconds (i.e. x1000)
        // as the BN API gives it (and requires it).

        // if value is nothing but 10 or 13 number chars...
        if (preg_match('@^\d+$@', $value) && in_array($valueLen, [10, 13])) {
            if ($valueLen == 13) {
                $value = substr($value, 0, -3);
            }

            $this->mustBeWithinThePastYear($value, $optionName);
        }
        else {
            try {
                $value = Carbon::parse($value, 'UTC')->timestamp;
                $this->mustBeWithinThePastYear($value, $optionName);
            }
            catch (Exception $e) {
                throw new Exception('Invalid ' . ($optionName ? "'$optionName' " : '') . 'date/time specified');
            }
        }

        return $value . '000';
    }

    /**
     * Throws an exception if $value is not a date within the past year
     *
     * @param int|string $value Unix timestamp (in seconds)
     * @param string $optionName e.g. 'start' or 'end', used for exception message if $value is invalid
     * @throws \Throwable
     */
    public function mustBeWithinThePastYear($value, $optionName)
    {
        throw_if(
            $value <= Carbon::parse('1 year ago')->timestamp || $value > Carbon::now()->timestamp,
            Exception::class,
            ('Invalid ' . ($optionName ? "'$optionName' " : '') . 'date/time specified')
        );
    }
}