<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, string $type = 'string', string $description = null, bool $isPublic = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => static::prepareValue($value, $type),
                'type' => $type,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Delete a setting by key.
     */
    public static function forget(string $key): bool
    {
        return static::where('key', $key)->delete() > 0;
    }

    /**
     * Get all settings as key-value pairs.
     */
    public static function getAllSettings(): array
    {
        return static::query()
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }

    /**
     * Get all public settings (accessible by non-admins).
     */
    public static function getAllPublicSettings(): array
    {
        return static::where('is_public', true)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }

    /**
     * Cast value based on type.
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'array' => json_decode($value, true),
            default => (string) $value,
        };
    }

    /**
     * Prepare value for storage based on type.
     */
    protected static function prepareValue(mixed $value, string $type): string|null
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }
}
