<?php

namespace App\Blls;

use App\Models\ExchangeInfo;
use Binance\API as BinanceApi;
use Illuminate\Support\Facades\Storage;

class ExchangeInfoBll
{
    protected $binanceApi;
    private static $excludedExchanges = [
        '123456',
    ];

    public function __construct(
        BinanceApi $binanceApi
    )
    {
        $this->binanceApi = $binanceApi;
    }

    public function fetchExchangeInfo()
    {
        return ExchangeInfo::all();
    }

    public function fetchRawExchangeInfo()
    {
        $filename = 'exchangeInfo.json';

        if (! Storage::disk('local')->exists($filename))
        {
            // Fetch from API
            $data = $api->exchangeInfo();

            Storage::put($filename, json_encode($data));
        }

        return json_decode(Storage::get($filename));
    }

    public function storeExchangeInfo($data)
    {
        $numSaved = 0;

        foreach ($data->symbols as $s)
        {
            if (in_array($s->symbol, self::$excludedExchanges)) {
                continue;
            }

            $ei = ExchangeInfo::firstOrNew([
                'symbol' => $s->symbol
            ]);

            $ei->symbol = $s->symbol;
            $ei->base_asset = $s->baseAsset;
            $ei->quote_asset = $s->quoteAsset;
            $ei->min_price = $s->filters[0]->minPrice;
            $ei->tick_size = $s->filters[0]->tickSize;
            $ei->min_qty = $s->filters[1]->minQty;
            $ei->step_size = $s->filters[1]->stepSize;
            $ei->min_notional = $s->filters[2]->minNotional;

            $ei->save();
            $numSaved++;
        }

        return $numSaved;
    }
}