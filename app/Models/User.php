<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CommandTemplate;
use App\Models\Device;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'status',
        'password',
'telegram_enabled',
'telegram_chat_id',
'telegram_bot_token',
'telegram_devices',
'telegram_ports',
'telegram_severities',
'telegram_event_types',
'telegram_template',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'telegram_enabled' => 'boolean',
        'telegram_chat_id' => 'string',
'telegram_bot_token' => 'string',
        'telegram_devices' => 'array',
        'telegram_ports' => 'string',
        'telegram_severities' => 'array',
        'telegram_event_types' => 'array',
        'telegram_template' => 'string',
        ];
    }

    public function devices()
    {
        return $this->hasMany(Device::class, 'assigned_user_id');
    }

    public function commandTemplates()
    {
        return $this->belongsToMany(CommandTemplate::class, 'command_template_user');
    }

    public function permittedDevices()
    {
        return $this->belongsToMany(Device::class, 'device_permissions')
            ->withPivot(['granted_by', 'granted_at', 'allowed_ports'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        if (($this->role ?? 'user') !== 'admin') {
            return false;
        }

        $identifiers = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => Str::lower(trim((string) $value)),
            config('admin.super_admin_identifiers', [])
        ), static fn (string $value): bool => $value !== '')));

        if ($identifiers === []) {
            return false;
        }

        $name = Str::lower(trim((string) ($this->name ?? '')));
        $email = Str::lower(trim((string) ($this->email ?? '')));

        return in_array($name, $identifiers, true) || in_array($email, $identifiers, true);
    }
}

