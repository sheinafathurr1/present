<?php

namespace App\Enums;

enum EmployeeType: string
{
    case Guru = 'guru';
    case Tendik = 'tendik';

    public function label(): string
    {
        return match ($this) {
            self::Guru => 'Guru',
            self::Tendik => 'Staf tendik',
        };
    }
}
