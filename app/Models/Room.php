<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'name',
        'location',
        'notes',
    ];

    public function cabinets()
    {
        return $this->hasMany(Cabinet::class)->orderBy('name');
    }
}
