<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = kehadiran satu guru/staf pada satu tanggal.
     * Lokasi GPS saat presensi masuk & pulang disimpan sebagai bukti.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 15); // hadir, terlambat, izin, sakit, cuti, dinas_luar, alpa

            $table->dateTime('check_in_at')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable(); // meter
            $table->unsignedInteger('check_in_distance')->nullable(); // meter dari sekolah

            $table->dateTime('check_out_at')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy')->nullable();
            $table->unsignedInteger('check_out_distance')->nullable();

            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_minutes')->default(0); // pulang lebih awal

            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 10)->default('gps'); // gps, pengajuan, admin, sistem
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
