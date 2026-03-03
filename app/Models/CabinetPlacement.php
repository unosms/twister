<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabinetPlacement extends Model
{
    protected $fillable = [
        'cabinet_id',
        'device_id',
        'start_u',
        'height_u',
        'face',
    ];

    protected $casts = [
        'start_u' => 'integer',
        'height_u' => 'integer',
    ];

    public function cabinet()
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
