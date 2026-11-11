<?php

namespace Tests\Feature;

use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_registers_student_with_card_and_photo(): void
    {
        Storage::fake('public');
        $class = SchoolClass::factory()->create();

        $this->actingAs($this->admin())->post('/siswa', [
            'nis' => '2024001',
            'name' => 'Budi Santoso',
            'nickname' => 'Budi',
            'gender' => 'L',
            'school_class_id' => $class->id,
            'disability_type' => 'A',
            'feedback_mode' => 'audio',
            'rfid_uid' => 'a1 b2 c3 d4',
            'photo' => UploadedFile::fake()->image('budi.jpg'),
            'is_active' => '1',
        ])->assertRedirect();

        $student = Student::sole();
        $this->assertSame('A1B2C3D4', $student->rfid_uid);
        $this->assertSame(DisabilityType::Tunanetra, $student->disability_type);
        $this->assertSame(FeedbackMode::Audio, $student->feedback_mode);
        Storage::disk('public')->assertExists($student->photo_path);
    }

    public function test_card_cannot_be_assigned_twice(): void
    {
        Student::factory()->create(['rfid_uid' => 'A1B2C3D4']);

        $this->actingAs($this->admin())->post('/siswa', [
            'nis' => '2024002',
            'name' => 'Siti',
            'feedback_mode' => 'both',
            'rfid_uid' => 'a1b2c3d4',
        ])->assertSessionHasErrors(['rfid_uid' => 'Kartu ini sudah dipakai siswa lain.']);
    }

    public function test_admin_can_update_and_deactivate_student(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin())->put("/siswa/{$student->id}", [
            'nis' => $student->nis,
            'name' => 'Nama Baru',
            'feedback_mode' => 'visual',
            'is_active' => '0',
        ])->assertRedirect("/siswa/{$student->id}");

        $student->refresh();
        $this->assertSame('Nama Baru', $student->name);
        $this->assertFalse($student->is_active);
    }

    public function test_teacher_cannot_create_students(): void
    {
        $this->actingAs(User::factory()->create())->post('/siswa', [
            'nis' => '1', 'name' => 'X', 'feedback_mode' => 'both',
        ])->assertForbidden();

        $this->assertSame(0, Student::count());
    }

    public function test_csv_import_creates_and_updates_students(): void
    {
        Student::factory()->create(['nis' => '2024001', 'name' => 'Nama Lama']);

        $csv = "\xEF\xBB\xBFnis;nama;nama_panggilan;jenis_kelamin;kelas;jenjang;jenis_disabilitas;uid_kartu;nama_wali;telepon_wali\n"
            ."2024001;Budi Santoso;Budi;L;Kelas 1A;SDLB;A;;Siti;0812\n"
            ."2024002;Rina Wulandari;Rina;P;Kelas 1A;SDLB;tunarungu;0004283792;Agus;0813\n"
            .";Tanpa NIS;;;;;;;;\n";

        $file = UploadedFile::fake()->createWithContent('siswa.csv', $csv);

        $this->actingAs($this->admin())
            ->post('/siswa/impor', ['file' => $file])
            ->assertRedirect('/siswa')
            ->assertSessionHas('success', 'Impor selesai: 1 siswa baru, 1 siswa diperbarui.');

        $this->assertSame('Budi Santoso', Student::where('nis', '2024001')->value('name'));
        $rina = Student::where('nis', '2024002')->first();
        $this->assertSame('0004283792', $rina->rfid_uid);
        $this->assertSame(DisabilityType::Tunarungu, $rina->disability_type);
        $this->assertSame(FeedbackMode::Visual, $rina->feedback_mode);
        $this->assertSame('SDLB', $rina->schoolClass->level);
        $this->assertSame(1, SchoolClass::count());
    }
}
