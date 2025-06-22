<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'ip_address',
        'successful',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'metadata' => 'array',
    ];

    public static function recordAttempt(string $email, string $ip, bool $successful, ?string $userAgent = null, array $metadata = []): self
    {
        return static::create([
            'email' => $email,
            'ip_address' => $ip,
            'successful' => $successful,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }

    public static function getRecentFailedAttempts(string $email, string $ip, int $minutes = 15): int
    {
        return static::where('email', $email)
            ->where('ip_address', $ip)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }
}