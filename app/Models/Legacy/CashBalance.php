<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the Windows program's `cash_balances` view: the income, expenses and net of a
 * cash box and of its project account. A view has no key, so rows are only listed. Read only.
 *
 * @property int $Account_ID
 * @property int|null $City_ID
 */
class CashBalance extends LegacyModel
{
    protected $table = 'cash_balances';

    protected $primaryKey = null;

    public $incrementing = false;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'Account_ID');
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'CashAccount_id');
    }
}
