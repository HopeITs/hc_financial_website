<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An income or expense operation (`action`). Read only.
 */
class Action extends LegacyModel
{
    protected $table = 'action';

    protected $primaryKey = 'ID';

    /**
     * @return BelongsTo<Till, $this>
     */
    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class, 'Till_ID');
    }

    /**
     * @return BelongsTo<Till, $this>
     */
    public function observeTill(): BelongsTo
    {
        return $this->belongsTo(Till::class, 'ObserveTill_ID');
    }

    /**
     * @return BelongsTo<TillType, $this>
     */
    public function tillType(): BelongsTo
    {
        return $this->belongsTo(TillType::class, 'currency_tilltype_id');
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'BudgetLine_ID');
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class, 'Donor_ID');
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cashaccount_id');
    }

    /**
     * @return BelongsTo<Rate, $this>
     */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'Rate_ID');
    }

    /**
     * @return BelongsTo<EmployeeSalary, $this>
     */
    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'Employee_Salary_ID');
    }

    /**
     * @return HasMany<ActionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ActionAttachment::class, 'action_id');
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'action_payment', 'action_id', 'payment_id');
    }
}
