<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'user_id', 'mobile', 'body', 'kind', 'status',
        'provider', 'provider_message_id', 'error', 'sent_at', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeQueued(Builder $q): Builder
    {
        return $q->where('status', 'queued');
    }
}
