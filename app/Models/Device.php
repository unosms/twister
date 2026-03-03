<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TelemetryLog;

class Device extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'type',
        'model',
        'serial_number',
        'status',
        'ip_address',
        'location',
        'firmware_version',
        'last_seen_at',
        'assigned_user_id',
        'metadata',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getMetadataAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function setMetadataAttribute($value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) {
                $this->attributes["metadata"] = json_encode($decoded);
                return;
            }
        }

        $this->attributes["metadata"] = json_encode(is_array($value) ? $value : []);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function telemetryLogs()
    {
        return $this->hasMany(TelemetryLog::class);
    }

    public function cabinetPlacement()
    {
        return $this->hasOne(CabinetPlacement::class);
    }

    public function permittedUsers()
    {
        return $this->belongsToMany(User::class, 'device_permissions')
            ->withPivot(['granted_by', 'granted_at', 'allowed_ports'])
            ->withTimestamps();
    }
}
