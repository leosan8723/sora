<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal key/value store for admin-editable site configuration
 * (contact info, supported consultation modes). Values are JSON encoded
 * so scalars and structured data both work.
 */
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** Read a setting, falling back to the given default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->find($key);

        if ($setting === null || ! array_key_exists('value', $setting->getAttributes())) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    /** Persist a setting. */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
