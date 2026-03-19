<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DeviceEventPermission extends Model
{
    protected $table = 'device_event_permissions';

    protected static ?bool $scopedAccessSupported = null;

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
}
