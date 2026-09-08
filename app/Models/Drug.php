<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    use HasFactory;

    protected $table = 'drugs';

    protected $fillable = ['name', 'item_order', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('item_order')->orderBy('name');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DrugVariant::class);
    }
}
