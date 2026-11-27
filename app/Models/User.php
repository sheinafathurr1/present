<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun guru, staf tendik, pimpinan, atau admin.
 *
 * Semua orang yang `must_attend` melakukan presensi dari HP-nya sendiri,
 * termasuk pimpinan. Admin murni (operator) bisa dibuat tanpa kewajiban presensi.
 */
#[Fillable([
    'name', 'username', 'email', 'password', 'role', 'employee_type', 'position',
    'nip', 'phone', 'must_attend', 'work_start', 'work_end', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'pegawai',
        'employee_type' => 'guru',
        'must_attend' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'employee_type' => EmployeeType::class,
            'must_attend' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Username selalu disimpan huruf kecil tanpa spasi di tepi. */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

            if ($role !== null && $this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Admin atau pimpinan: boleh melihat kehadiran semua orang. */
    public function canMonitor(): bool
    {
        return $this->role->canMonitor();
    }

    /** Wajib presensi dari HP. */
    public function attends(): bool
    {
        return $this->is_active && $this->must_attend;
    }

    /** Halaman pertama setelah login. */
    public function homeRoute(): string
    {
        return $this->canMonitor() ? route('dashboard') : route('presence.show');
    }

    /** Keterangan singkat: jabatan, atau jenis pegawai. */
    public function subtitle(): string
    {
        return $this->position ?: $this->employee_type->label();
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/^(Bapak|Ibu|Bpk\.?|Bu|Pak)\s+/i', '', $this->name))) ?: [];
        $letters = array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    /**
     * Orang yang wajib presensi (aktif & tidak dikecualikan).
     *
     * @param  Builder<User>  $query
     */
    public function scopeAttending(Builder $query): void
    {
        $query->where('is_active', true)->where('must_attend', true);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
