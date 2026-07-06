<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $fillable = ['sum', 'paid_sum', 'bonus_sum', 'promo_code_id', 'status', 'source'];

    protected $dates = ['credited_at'];

    public $statuses = [
        0 => 'Платеж не прошел',
        1 => 'Пополнение',
        2 => 'Расход',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function isTopUp(): bool
    {
        return (int) $this->status === 1;
    }

    public function isExpense(): bool
    {
        return (int) $this->status === 2;
    }

    public function isFailed(): bool
    {
        return (int) $this->status === 0;
    }

    /** Изменение баланса по этой операции (неуспешные платежи — 0). */
    public function ledgerSignedDelta(): int
    {
        if ($this->isTopUp()) {
            return (int) $this->sum;
        }

        if ($this->isExpense()) {
            return -(int) $this->sum;
        }

        return 0;
    }

    public function ledgerBalanceAfter(): ?int
    {
        if (!array_key_exists('ledger_balance_after', $this->attributes)) {
            return null;
        }

        return (int) $this->attributes['ledger_balance_after'];
    }

    public function ledgerBalanceBefore(): ?int
    {
        $after = $this->ledgerBalanceAfter();

        return $after === null ? null : $after - $this->ledgerSignedDelta();
    }
}
