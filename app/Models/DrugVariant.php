<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'drug_id', 'name', 'default_dosage', 'default_instructions',
        'item_order', 'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('item_order')->orderBy('name');
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->drug?->name ?? '').' — '.$this->name, ' —');
    }
}
