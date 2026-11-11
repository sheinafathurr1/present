<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'level', 'homeroom_teacher_id', 'late_after', 'check_out_start'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    public const LEVELS = ['TKLB', 'SDLB', 'SMPLB', 'SMALB'];

    /** Urutan tampil: TKLB, SDLB, SMPLB, SMALB, lalu yang tanpa jenjang. */
    public function levelOrder(): int
    {
        $index = array_search($this->level, self::LEVELS, true);

        return $index === false ? count(self::LEVELS) : $index;
    }

    /** @return BelongsTo<User, $this> */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
