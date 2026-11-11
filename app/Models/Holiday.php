<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Kolom `date` disimpan sebagai string "Y-m-d" (lihat catatan di Attendance). */
#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    public static function isHoliday(string $date): bool
    {
        return static::where('date', $date)->exists();
    }
}
