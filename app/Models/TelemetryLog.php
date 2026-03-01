<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryLog extends Model
{
    protected $fillable = [
        'device_id',
        'level',
        'message',
        'payload',
        'recorded_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
