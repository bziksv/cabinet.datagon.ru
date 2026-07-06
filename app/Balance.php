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
}
