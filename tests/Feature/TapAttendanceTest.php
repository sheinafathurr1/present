<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ScanResult;
use App\Events\AttendanceRecorded;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\RfidScan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TapAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    private Device $device;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceService::class);
        $this->device = Device::factory()->create();
        $this->student = Student::factory()->create(['rfid_uid' => 'A1B2C3D4', 'nickname' => 'Budi']);
    }

    private function at(string $time, string $date = '2026-11-11'): Carbon
    {
        // 11 November 2026 = Rabu (hari sekolah).
        return Carbon::parse("{$date} {$time}");
    }

    public function test_first_tap_before_late_limit_is_on_time_check_in(): void
    {
        $outcome = $this->service->handleTap('a1b2c3d4', $this->device, $this->at('06:58'));

        $this->assertSame(ScanResult::CheckIn, $outcome->result);
        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame('2026-11-11', $attendance->date);
        $this->assertSame('06:58', $attendance->check_in_at->format('H:i'));
        $this->assertSame($this->device->id, $attendance->check_in_device_id);
        $this->assertStringContainsString('Selamat pagi, Budi', $outcome->message());
    }

    public function test_tap_in_the_late_limit_minute_is_still_on_time(): void
    {
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:15:59'));

        $this->assertSame(ScanResult::CheckIn, $outcome->result);
    }

    public function test_tap_after_late_limit_is_marked_late(): void
    {
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:16'));

        $this->assertSame(ScanResult::Late, $outcome->result);
        $this->assertSame(AttendanceStatus::Terlambat, Attendance::sole()->status);
    }

    public function test_uid_is_normalized_from_reader_formats(): void
    {
        $outcome = $this->service->handleTap(' a1:b2:c3:d4 ', $this->device, $this->at('07:00'));

        $this->assertSame(ScanResult::CheckIn, $outcome->result);
    }

    public function test_repeated_tap_within_cooldown_is_ignored(): void
    {
        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00:00'));
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00:30'));

        $this->assertSame(ScanResult::Duplicate, $outcome->result);
        $this->assertSame(1, Attendance::count());
        $this->assertSame(2, RfidScan::count());
    }

    public function test_second_tap_before_check_out_time_only_informs(): void
    {
        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00'));
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('09:00'));

        $this->assertSame(ScanResult::AlreadyIn, $outcome->result);
        $this->assertNull(Attendance::sole()->check_out_at);
        $this->assertStringContainsString('12.00', $outcome->message());
    }

    public function test_second_tap_after_check_out_time_records_check_out(): void
    {
        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00'));
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('12:05'));

        $this->assertSame(ScanResult::CheckOut, $outcome->result);
        $this->assertSame('12:05', Attendance::sole()->check_out_at->format('H:i'));
        $this->assertSame(AttendanceStatus::Hadir, Attendance::sole()->status);

        $again = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('13:00'));
        $this->assertSame(ScanResult::AlreadyOut, $again->result);
    }

    public function test_class_schedule_overrides_school_schedule(): void
    {
        $class = SchoolClass::factory()->create(['late_after' => '07:30', 'check_out_start' => '10:30']);
        $this->student->update(['school_class_id' => $class->id]);

        $this->assertSame(ScanResult::CheckIn, $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:25'))->result);
        $this->assertSame(ScanResult::CheckOut, $this->service->handleTap('A1B2C3D4', $this->device, $this->at('10:35'))->result);
    }

    public function test_tap_on_holiday_is_never_late(): void
    {
        Holiday::create(['date' => '2026-11-11', 'name' => 'Libur contoh']);

        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('09:00'));

        $this->assertSame(ScanResult::CheckIn, $outcome->result);
    }

    public function test_tap_on_sunday_is_never_late(): void
    {
        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('09:00', '2026-11-15'));

        $this->assertSame(ScanResult::CheckIn, $outcome->result);
    }

    public function test_tap_turns_earlier_sick_note_into_presence(): void
    {
        Attendance::create([
            'student_id' => $this->student->id, 'date' => '2026-11-11',
            'status' => AttendanceStatus::Sakit, 'source' => Attendance::SOURCE_MANUAL, 'note' => 'Pusing',
        ]);

        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('08:00'));

        $this->assertSame(ScanResult::Late, $outcome->result);
        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Terlambat, $attendance->status);
        $this->assertSame('Pusing', $attendance->note);
    }

    public function test_unknown_card_is_logged_for_registration(): void
    {
        $outcome = $this->service->handleTap('FFEE0011', $this->device, $this->at('07:00'));

        $this->assertSame(ScanResult::UnknownCard, $outcome->result);
        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseHas('rfid_scans', ['uid' => 'FFEE0011', 'result' => 'unknown_card', 'student_id' => null]);
    }

    public function test_inactive_student_cannot_check_in(): void
    {
        $this->student->update(['is_active' => false]);

        $outcome = $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00'));

        $this->assertSame(ScanResult::Inactive, $outcome->result);
        $this->assertSame(0, Attendance::count());
    }

    public function test_event_is_dispatched_only_for_recorded_taps(): void
    {
        Event::fake([AttendanceRecorded::class]);

        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00'));
        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00:10'));
        $this->service->handleTap('UNKNOWN1', $this->device, $this->at('07:01'));

        Event::assertDispatchedTimes(AttendanceRecorded::class, 1);
    }

    public function test_device_last_seen_is_updated(): void
    {
        $this->service->handleTap('A1B2C3D4', $this->device, $this->at('07:00'));

        $this->assertSame('07:00', $this->device->fresh()->last_seen_at->format('H:i'));
    }
}
