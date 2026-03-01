<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];
}
