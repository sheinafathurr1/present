<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu 11 November 2026. Hari sekolah Senin–Sabtu.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(6, 30));
        $this->setUpSchool();
        Storage::fake('local');
    }

    private function submit(User $user, array $data)
    {
        return $this->actingAs($user)->post('/izin', [
            'type' => 'izin',
            'start_date' => '2026-11-11',
            'reason' => 'Keperluan keluarga',
            ...$data,
        ]);
    }

    public function test_leave_is_recorded_immediately_for_school_days_only(): void
    {
        $guru = User::factory()->create();
        Holiday::create(['date' => '2026-11-13', 'name' => 'Libur contoh']);

        $this->submit($guru, [
            'type' => 'sakit',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-16',
            'reason' => 'Demam, istirahat sesuai saran dokter',
            'attachment' => UploadedFile::fake()->image('surat-dokter.jpg', 800, 1000),
        ])->assertRedirect('/izin')
            ->assertSessionHas('success', 'Sakit 12–16 Nov 2026 tercatat untuk 3 hari sekolah.');

        $leave = LeaveRequest::sole();
        $this->assertSame(LeaveType::Sakit, $leave->type);
        Storage::disk('local')->assertExists($leave->attachment_path);
        $this->assertStringStartsWith('lampiran-izin/'.$guru->id.'/', $leave->attachment_path);

        // Kamis, Sabtu, Senin tercatat; Jumat libur & Minggu dilewati.
        $this->assertSame(['2026-11-12', '2026-11-14', '2026-11-16'], Attendance::orderBy('date')->pluck('date')->all());
        $this->assertTrue(Attendance::all()->every(fn (Attendance $a) => $a->status === AttendanceStatus::Sakit
            && $a->leave_request_id === $leave->id && $a->source === Attendance::SOURCE_LEAVE));
    }

    public function test_days_already_checked_in_are_not_overwritten(): void
    {
        $guru = User::factory()->create();
        $this->actingAs($guru)->post('/presensi/masuk', $this->gps());

        $this->submit($guru, ['type' => 'dinas_luar', 'end_date' => '2026-11-12', 'reason' => 'Rapat KKG di kecamatan'])
            ->assertSessionHas('success', 'Dinas luar 11–12 Nov 2026 tercatat untuk 1 hari sekolah. 11 Nov tidak diubah karena Anda sudah presensi masuk.');

        $this->assertSame(AttendanceStatus::Hadir, $guru->attendances()->where('date', '2026-11-11')->value('status'));
        $this->assertSame(AttendanceStatus::DinasLuar, $guru->attendances()->where('date', '2026-11-12')->value('status'));
    }

    public function test_leave_overwrites_an_absent_mark(): void
    {
        $guru = User::factory()->create();
        Attendance::factory()->for($guru)->create(['date' => '2026-11-10', 'status' => AttendanceStatus::Alpa, 'check_in_at' => null, 'source' => Attendance::SOURCE_SYSTEM]);

        $this->submit($guru, ['type' => 'sakit', 'start_date' => '2026-11-10', 'reason' => 'Sakit, baru sempat lapor'])->assertSessionHas('success');

        $this->assertSame(AttendanceStatus::Sakit, $guru->attendances()->sole()->status);
    }

    public function test_invalid_requests_are_rejected(): void
    {
        $guru = User::factory()->create();
        LeaveRequest::factory()->for($guru)->create(['start_date' => '2026-11-12', 'end_date' => '2026-11-13']);

        $this->submit($guru, ['start_date' => '2026-11-13', 'end_date' => '2026-11-14'])
            ->assertSessionHasErrors(['start_date' => 'Sudah ada pengajuan Izin pada 12–13 Nov 2026. Batalkan dulu bila ingin mengubahnya.']);
        $this->submit($guru, ['start_date' => '2026-11-01'])->assertSessionHasErrors('start_date');
        $this->submit($guru, ['start_date' => '2026-11-20', 'end_date' => '2026-11-18'])->assertSessionHasErrors('end_date');
        $this->submit($guru, ['start_date' => '2026-11-15'])
            ->assertSessionHasErrors(['start_date' => 'Tidak ada hari sekolah pada tanggal itu (libur atau akhir pekan), jadi tidak perlu mengajukan.']);
        $this->submit($guru, ['type' => 'liburan'])->assertSessionHasErrors('type');
        $this->submit($guru, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->submit($guru, ['attachment' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('attachment');

        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_presence_is_blocked_while_on_leave(): void
    {
        $guru = User::factory()->create();
        $this->submit($guru, ['type' => 'cuti', 'reason' => 'Cuti tahunan']);

        $this->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Hari ini Anda tercatat Cuti. Batalkan pengajuannya di menu Izin bila ternyata masuk.');
        $this->get('/presensi')->assertSee('Hari ini Anda tercatat Cuti');
    }

    public function test_cancelling_a_leave_that_has_not_passed_removes_it(): void
    {
        $guru = User::factory()->create();
        $this->submit($guru, ['start_date' => '2026-11-12', 'end_date' => '2026-11-14', 'attachment' => UploadedFile::fake()->image('surat.jpg')]);
        $leave = LeaveRequest::sole();

        $this->delete("/izin/{$leave->id}")->assertRedirect('/izin')->assertSessionHas('success', 'Izin 12–14 Nov 2026 dibatalkan.');

        $this->assertSame(0, LeaveRequest::count());
        $this->assertSame(0, Attendance::count());
        Storage::disk('local')->assertMissing($leave->attachment_path);
    }

    public function test_ending_an_ongoing_leave_keeps_the_days_that_passed(): void
    {
        $guru = User::factory()->create();
        $this->travelTo(now()->setDate(2026, 11, 9)); // Senin
        $this->submit($guru, ['type' => 'sakit', 'start_date' => '2026-11-09', 'end_date' => '2026-11-13']);

        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0)); // sudah sembuh hari Rabu
        $this->delete('/izin/'.LeaveRequest::sole()->id)
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'diakhiri'));

        $this->assertSame('2026-11-10', LeaveRequest::sole()->end_date);
        $this->assertSame(['2026-11-09', '2026-11-10'], Attendance::orderBy('date')->pluck('date')->all());

        $this->post('/presensi/masuk', $this->gps())->assertSessionHas('success');
        $this->assertSame(AttendanceStatus::Terlambat, $guru->attendances()->where('date', '2026-11-11')->value('status'));
    }

    public function test_past_leave_and_other_peoples_leave_cannot_be_cancelled(): void
    {
        $guru = User::factory()->create();
        $past = LeaveRequest::factory()->for($guru)->create(['start_date' => '2026-11-09', 'end_date' => '2026-11-10']);
        $other = LeaveRequest::factory()->create();

        $this->actingAs($guru)->delete("/izin/{$past->id}")->assertSessionHas('error');
        $this->delete("/izin/{$other->id}")->assertForbidden();

        $this->assertSame(2, LeaveRequest::count());
    }

    public function test_attachment_is_private(): void
    {
        $guru = User::factory()->create();
        $this->submit($guru, ['attachment' => UploadedFile::fake()->image('surat.jpg')]);
        $leave = LeaveRequest::sole();
        $url = "/izin/{$leave->id}/lampiran";

        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->pimpinan()->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_leave_pages(): void
    {
        $guru = User::factory()->create();

        $this->actingAs($guru)->get('/izin')->assertOk()->assertSee('Belum ada pengajuan');
        $this->get('/izin/ajukan')->assertOk()->assertSee('Dinas luar')->assertSee('min="2026-11-04"', false);

        $this->submit($guru, ['reason' => 'Mengantar anak berobat']);
        $this->get('/izin')->assertOk()->assertSee('Mengantar anak berobat')->assertSee('Sedang berlangsung')->assertSee('1 hari sekolah');
    }
}
