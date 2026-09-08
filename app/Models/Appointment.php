<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['scheduled', 'confirmed', 'done', 'cancelled', 'no_show'];

    protected $fillable = [
        'patient_id', 'user_id', 'treatment_service_id', 'scheduled_on',
        'starts_at', 'duration_minutes', 'status', 'notes', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'duration_minutes' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TreatmentService::class, 'treatment_service_id');
    }

    public function scopeOn(Builder $q, string $date): Builder
    {
        return $q->whereDate('scheduled_on', $date);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereDate('scheduled_on', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed']);
    }
}
