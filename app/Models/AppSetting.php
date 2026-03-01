<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function supportsStorage(): bool
    {
        try {
            return Schema::hasTable('app_settings')
                && Schema::hasColumn('app_settings', 'key')
                && Schema::hasColumn('app_settings', 'value');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (!static::supportsStorage()) {
            return $default;
        }

        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function putValue(string $key, mixed $value): self
    {
        if (!static::supportsStorage()) {
            throw new \RuntimeException('The app_settings table does not have the required key/value columns.');
        }

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? $value : json_encode($value)]
        );
    }
}
