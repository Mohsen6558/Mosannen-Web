<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The daily visit counter printed on the patient's slip. The legacy app
 * incremented a row per day; the same idea, done atomically.
 */
class Visit extends Model
{
    protected $fillable = ['visit_date', 'last_number'];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'last_number' => 'integer'];
    }

    /** Reserve and return the next visit number for a given date. */
    public static function nextNumber(?string $date = null): int
    {
        $date ??= now()->toDateString();

        return DB::transaction(function () use ($date) {
            $visit = static::query()->lockForUpdate()->firstOrCreate(
                ['visit_date' => $date],
                ['last_number' => 0],
            );

            $visit->increment('last_number');

            return (int) $visit->last_number;
        });
    }
}
