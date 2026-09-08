<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Radiograph extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id', 'user_id', 'taken_on', 'subject', 'disk', 'path',
        'original_name', 'thumbnail_path', 'mime', 'size', 'width', 'height',
        'checksum', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'size' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(RadiographTooth::class);
    }

    /** @return list<string> */
    public function getToothCodesAttribute(): array
    {
        return $this->teeth->pluck('tooth_code')->all();
    }

    public function syncTeeth(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        $this->teeth()->whereNotIn('tooth_code', $codes ?: ['--'])->delete();
        $existing = $this->teeth()->pluck('tooth_code')->all();

        foreach (array_diff($codes, $existing) as $code) {
            $this->teeth()->create(['tooth_code' => $code]);
        }
    }

    /** Files are private; this is the only handle the app hands out. */
    public function storage()
    {
        return Storage::disk($this->disk ?: config('clinic.images.disk'));
    }

    public function exists(): bool
    {
        return $this->path && $this->storage()->exists($this->path);
    }
}
