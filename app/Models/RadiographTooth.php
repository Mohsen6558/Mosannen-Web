<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiographTooth extends Model
{
    protected $fillable = ['radiograph_id', 'tooth_code'];

    public function radiograph(): BelongsTo
    {
        return $this->belongsTo(Radiograph::class);
    }
}
