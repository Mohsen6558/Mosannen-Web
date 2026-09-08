<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    use HasFactory;

    protected $table = 'stock_items';

    protected $fillable = ['name', 'unit', 'reorder_level', 'item_order', 'is_active', 'legacy_id'];

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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Stock on hand, derived from the append-only movement ledger. */
    public function getOnHandAttribute(): int
    {
        return (int) $this->movements()->where('direction', 'in')->sum('quantity')
             - (int) $this->movements()->where('direction', 'out')->sum('quantity');
    }
}
