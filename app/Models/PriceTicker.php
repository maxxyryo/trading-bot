<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 18 Jan 2018 05:03:33 +0800.
 */

namespace App\Models;

use Reliese\Database\Eloquent\Model as Eloquent;

/**
 * Class PriceTicker
 * 
 * @property int $id
 * @property string $symbol
 * @property \Carbon\Carbon $datetime_
 * @property float $price
 * @property float $perc_change_vs_1
 * @property float $perc_change_vs_2
 * @property float $perc_change_vs_3
 * @property float $perc_change_vs_4
 * @property float $ma4
 * @property float $ma9
 * @property float $ma20
 * @property float $ema4
 * @property float $ema5
 * @property float $ema9
 * @property float $mapc3
 * @property float $mapc4
 * @property float $mapc5
 * @property float $mapc6
 * @property float $emapc3
 * @property float $emapc4
 * @property float $emapc5
 * @property float $emapc6
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @package App\Models
 */
class PriceTicker extends Eloquent
{
	protected $table = 'price_ticker';

	protected $casts = [
		'price' => 'float',
		'perc_change_vs_1' => 'float',
		'perc_change_vs_2' => 'float',
		'perc_change_vs_3' => 'float',
		'perc_change_vs_4' => 'float',
		'ma4' => 'float',
		'ma9' => 'float',
		'ma20' => 'float',
		'ema4' => 'float',
		'ema5' => 'float',
		'ema9' => 'float',
		'mapc3' => 'float',
		'mapc4' => 'float',
		'mapc5' => 'float',
		'mapc6' => 'float',
		'emapc3' => 'float',
		'emapc4' => 'float',
		'emapc5' => 'float',
		'emapc6' => 'float'
	];

	protected $dates = [
		'datetime_'
	];

	protected $fillable = [
		'symbol',
		'datetime_',
		'price',
		'perc_change_vs_1',
		'perc_change_vs_2',
		'perc_change_vs_3',
		'perc_change_vs_4',
		'ma4',
		'ma9',
		'ma20',
		'ema4',
		'ema5',
		'ema9',
		'mapc3',
		'mapc4',
		'mapc5',
		'mapc6',
		'emapc3',
		'emapc4',
		'emapc5',
		'emapc6'
	];
}
