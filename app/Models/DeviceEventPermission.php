<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DeviceEventPermission extends Model
{
    protected $table = 'device_event_permissions';

    protected static ?bool $scopedAccessSupported = null;
    protected static ?bool $interfaceScopeSupported = null;

    public static function supportsScopedAccess(): bool
    {
        if (static::$scopedAccessSupported !== null) {
            return static::$scopedAccessSupported;
        }

        try {
            static::$scopedAccessSupported = Schema::hasTable('device_event_permissions')
                && Schema::hasColumn('device_event_permissions', 'device_id');
        } catch (\Throwable) {
            static::$scopedAccessSupported = false;
        }

        return static::$scopedAccessSupported;
    }

    public static function supportsInterfaceScope(): bool
    {
        if (static::$interfaceScopeSupported !== null) {
            return static::$interfaceScopeSupported;
        }

        try {
            static::$interfaceScopeSupported = Schema::hasTable('device_event_permissions')
                && Schema::hasColumn('device_event_permissions', 'allowed_interfaces');
        } catch (\Throwable) {
            static::$interfaceScopeSupported = false;
        }

        return static::$interfaceScopeSupported;
    }
}
