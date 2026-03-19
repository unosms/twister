<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DeviceGraphPermission extends Model
{
    protected $table = 'device_graph_permissions';

    protected static ?bool $scopedAccessSupported = null;

    public static function supportsScopedAccess(): bool
    {
        if (static::$scopedAccessSupported !== null) {
            return static::$scopedAccessSupported;
        }

        try {
            static::$scopedAccessSupported = Schema::hasTable('device_graph_permissions')
                && Schema::hasColumn('device_graph_permissions', 'allowed_interfaces');
        } catch (\Throwable) {
            static::$scopedAccessSupported = false;
        }

        return static::$scopedAccessSupported;
    }
}
