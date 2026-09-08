<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_item_id', 'user_id', 'direction', 'quantity',
        'moved_on', 'description', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['moved_on' => 'date', 'quantity' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($q) => $q->whereDate('moved_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('moved_on', '<=', $to));
    }
}
