<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandExecution extends Model
{
    protected $fillable = [
        'command_template_id',
        'device_id',
        'executed_by',
        'status',
        'payload',
        'result',
        'error_message',
        'executed_at',
    ];
}
