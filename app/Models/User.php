<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CommandTemplate;
use App\Models\Device;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static ?bool $avatarStorageSupported = null;

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
        'avatar_path',
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

    public static function supportsAvatarStorage(): bool
    {
        if (static::$avatarStorageSupported !== null) {
            return static::$avatarStorageSupported;
        }

        try {
            static::$avatarStorageSupported = Schema::hasTable('users')
                && Schema::hasColumn('users', 'avatar_path');
        } catch (\Throwable) {
            static::$avatarStorageSupported = false;
        }

        return static::$avatarStorageSupported;
    }

    public function profileAvatarUrl(): ?string
    {
        if (!static::supportsAvatarStorage()) {
            return null;
        }

        $path = trim((string) ($this->avatar_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return asset(ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function profileInitials(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, static fn ($part): bool => $part !== ''));
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= Str::upper(Str::substr($part, 0, 1));
        }

        if ($initials === '') {
            $initials = Str::upper(Str::substr($name, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }
}

