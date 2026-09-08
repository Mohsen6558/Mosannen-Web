<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treatment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'treatment_service_id', 'user_id',
        'performed_on', 'amount', 'description', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TreatmentService::class, 'treatment_service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(TreatmentTooth::class);
    }

    /** @return list<string> FDI codes this treatment was performed on. */
    public function getToothCodesAttribute(): array
    {
        return $this->teeth->pluck('tooth_code')->all();
    }

    /** Replace the tooth set in one go; used by both create and update. */
    public function syncTeeth(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        $this->teeth()->whereNotIn('tooth_code', $codes ?: ['--'])->delete();

        $existing = $this->teeth()->pluck('tooth_code')->all();

        foreach (array_diff($codes, $existing) as $code) {
            $this->teeth()->create(['tooth_code' => $code]);
        }
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($q) => $q->whereDate('performed_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('performed_on', '<=', $to));
    }
}
