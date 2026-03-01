<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'alert_id',
        'type',
        'title',
        'body',
        'severity',
        'read_at',
        'archived_at',
        'metadata',
    ];
}
