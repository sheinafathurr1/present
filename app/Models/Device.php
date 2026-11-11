<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Pembaca kartu jaringan (ESP32/ESP8266) yang mengirim tap lewat API.
 * Pembaca USB yang dicolok ke PC guru tidak memerlukan data perangkat.
 */
#[Fillable(['name', 'location', 'is_active'])]
#[Hidden(['api_token_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Buat token API baru. Token asli hanya dikembalikan sekali; yang disimpan
     * di database hanyalah hash SHA-256-nya.
     */
    public function issueToken(): string
    {
        $token = 'dev_'.Str::random(40);

        $this->forceFill(['api_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('api_token_hash', hash('sha256', $token))->first();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(10));
    }

    /** @return HasMany<RfidScan, $this> */
    public function scans(): HasMany
    {
        return $this->hasMany(RfidScan::class);
    }
}
