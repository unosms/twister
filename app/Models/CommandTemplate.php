<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CommandTemplate extends Model
{
    protected $fillable = [
        'name',
        'action_key',
        'description',
        'device_group_id',
        'ui_type',
        'payload_template',
        'requires_confirmation',
        'requires_2fa',
        'log_execution',
        'active',
        'created_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'command_template_user');
    }
}
