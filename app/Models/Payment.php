<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'payment_type_id', 'user_id',
        'paid_on', 'paid_at', 'amount', 'discount', 'description', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
            'discount' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Amount + discount: how much of the patient's debt this settled. */
    public function getSettledAttribute(): int
    {
        return (int) $this->amount + (int) $this->discount;
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($q) => $q->whereDate('paid_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_on', '<=', $to));
    }
}
