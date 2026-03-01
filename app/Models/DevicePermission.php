<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DevicePermission extends Model
{
    protected $table = 'device_permissions';

    protected static ?bool $allowedCommandTemplateSupport = null;

    public static function supportsAllowedCommandTemplateIds(): bool
    {
        if (static::$allowedCommandTemplateSupport !== null) {
            return static::$allowedCommandTemplateSupport;
        }

        try {
            static::$allowedCommandTemplateSupport = Schema::hasTable('device_permissions')
                && Schema::hasColumn('device_permissions', 'allowed_command_template_ids');
        } catch (\Throwable) {
            static::$allowedCommandTemplateSupport = false;
        }

        return static::$allowedCommandTemplateSupport;
    }

    public static function decodeAllowedCommandTemplateIds(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
        } else {
            $decoded = [];
        }

        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($decoded, static fn ($id): bool => is_numeric($id) && (int) $id > 0)
        )));
    }

    public static function encodeAllowedCommandTemplateIds(array $ids): ?string
    {
        $normalized = static::decodeAllowedCommandTemplateIds($ids);

        return empty($normalized) ? null : json_encode($normalized);
    }
}
