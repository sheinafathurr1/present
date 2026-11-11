<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('level', 20)->nullable(); // SDLB, SMPLB, SMALB
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            // Jadwal khusus kelas (kosong = ikut pengaturan sekolah).
            $table->string('late_after', 5)->nullable();
            $table->string('check_out_start', 5)->nullable();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
