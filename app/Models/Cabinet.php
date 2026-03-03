<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabinet extends Model
{
    protected $fillable = [
        'room_id',
        'name',
        'size_u',
        'manufacturer',
        'model',
    ];

    protected $casts = [
        'size_u' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function placements()
    {
        return $this->hasMany(CabinetPlacement::class)->orderBy('face')->orderByDesc('start_u');
    }
}
