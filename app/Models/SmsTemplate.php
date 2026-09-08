<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['key', 'name', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Render the template with {{placeholder}} substitution.
     * Unknown placeholders are stripped rather than left visible to a patient.
     */
    public function render(array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn ($m) => (string) ($data[$m[1]] ?? ''),
            $this->body,
        );
    }

    public static function for(string $key): ?self
    {
        return static::query()->where('key', $key)->where('is_active', true)->first();
    }
}
