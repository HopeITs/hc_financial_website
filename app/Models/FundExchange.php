<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An exchange between an instalment fund (a funder's repayments in a city) and the organisation (finance decision of
 * 2026-11-22), made by the Financial Manager:
 *
 * - "fix": the fund's pounds go to the city's general account and dollars of the organisation are set aside for the
 *   fund at the day's rate, so their value is fixed; later the organisation gives back the same pounds and takes its
 *   dollars again (a saving for the organisation when the pound has fallen);
 * - "exchange": the same, for good, to fund a loan in dollars from a fund in pounds.
 *
 * @property int $id
 * @property string $funder
 * @property string $city
 * @property string $kind self::FIX or self::EXCHANGE
 * @property string $syp_amount
 * @property string $usd_amount
 * @property string $rate
 * @property Carbon $fixed_on
 * @property string $syp_account
 * @property string $usd_account
 * @property string $voucher_id
 * @property Carbon|null $returned_on
 * @property string|null $return_rate
 * @property string|null $return_voucher_id
 * @property int $created_by
 */
class FundExchange extends Model
{
    public const FIX = 'fix';

    public const EXCHANGE = 'exchange';

    protected $table = 'gl_fund_exchanges';

    protected $fillable = [
        'funder', 'city', 'kind', 'syp_amount', 'usd_amount', 'rate', 'fixed_on', 'syp_account', 'usd_account', 'voucher_id',
        'returned_on', 'return_rate', 'return_voucher_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'syp_amount' => 'decimal:2',
            'usd_amount' => 'decimal:2',
            'rate' => 'decimal:6',
            'return_rate' => 'decimal:6',
            'fixed_on' => 'date',
            'returned_on' => 'date',
            'created_by' => 'integer',
        ];
    }

    /** A fix not given back yet. */
    public function isOpen(): bool
    {
        return $this->kind === self::FIX && $this->return_voucher_id === null;
    }
}
