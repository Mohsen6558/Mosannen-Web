<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::rememberForever("setting.$key", fn () => static::find($key));

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'int' => (int) $row->value,
            'bool' => filter_var($row->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $row->value, true),
            default => $row->value,
        };
    }

    public static function put(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $type === 'json' ? json_encode($value) : (string) $value, 'type' => $type],
        );

        Cache::forget("setting.$key");
    }
}
