<?php

namespace App\Models;

use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'nis', 'nisn', 'name', 'nickname', 'gender', 'school_class_id', 'disability_type',
    'feedback_mode', 'rfid_uid', 'guardian_name', 'guardian_phone', 'notes', 'photo_path', 'is_active',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $attributes = [
        'feedback_mode' => 'both',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'disability_type' => DisabilityType::class,
            'feedback_mode' => FeedbackMode::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Samakan format UID kartu: huruf besar, tanpa spasi/titik dua/tanda hubung.
     * Pembaca USB dan pembaca jaringan bisa mengirim format yang sedikit berbeda.
     */
    public static function normalizeUid(?string $uid): ?string
    {
        $uid = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $uid));

        return $uid === '' ? null : $uid;
    }

    protected function rfidUid(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizeUid($value));
    }

    /** Nama untuk sapaan saat tap kartu: nama panggilan, atau kata pertama nama lengkap. */
    public function callName(): string
    {
        return $this->nickname ?: Str::before($this->name, ' ');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** @param  Builder<Student>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
