<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device;

class Alert extends Model
{
    protected $fillable = [
        'device_id',
        'severity',
        'title',
        'message',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
