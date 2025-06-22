<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'phone_verified',
        'two_factor_enabled',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'phone_verified' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_recovery_codes' => 'encrypted:array',
        'password' => 'hashed',
    ];

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'username'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function generateTwoFactorSecret()
    {
        $this->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
        ])->save();
    }

    public function confirmTwoFactorAuth()
    {
        $this->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function hasVerifiedTwoFactor(): bool
    {
        return $this->two_factor_enabled && !is_null($this->two_factor_confirmed_at);
    }
}