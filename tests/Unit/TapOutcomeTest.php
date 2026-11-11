<?php

namespace Tests\Unit;

use App\Enums\ScanResult;
use App\Models\Student;
use App\Services\TapOutcome;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TapOutcomeTest extends TestCase
{
    public function test_spoken_time_is_natural_indonesian(): void
    {
        $this->assertSame('pukul 7 lewat 5 menit', TapOutcome::spokenTime(Carbon::parse('2026-11-11 07:05')));
        $this->assertSame('pukul 12 tepat', TapOutcome::spokenTime('12:00'));
        $this->assertSame('', TapOutcome::spokenTime(null));
    }

    public function test_lcd_lines_are_ascii_and_at_most_16_chars(): void
    {
        $student = new Student(['name' => 'Muhammad Fadhlurrahman Ālamsyah', 'nickname' => 'Muhammad Fadhlurrahman']);
        $outcome = new TapOutcome(ScanResult::Late, 'A1', Carbon::parse('2026-11-11 07:40'), $student);

        [$line1, $line2] = $outcome->displayLines();

        $this->assertSame('Muhammad Fadhlur', $line1);
        $this->assertSame('TERLAMBAT 07:40', $line2);
    }

    public function test_call_name_falls_back_to_first_name(): void
    {
        $this->assertSame('Rina', (new Student(['name' => 'Rina Wulandari']))->callName());
        $this->assertSame('RW', (new Student(['name' => 'Rina Wulandari']))->initials());
    }

    public function test_uid_normalization(): void
    {
        $this->assertSame('A1B2C3D4', Student::normalizeUid(' a1:b2-c3 d4 '));
        $this->assertNull(Student::normalizeUid('  '));
    }
}
