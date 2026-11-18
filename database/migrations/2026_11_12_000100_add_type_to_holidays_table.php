<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            // nasional, cuti_bersama, atau sekolah (ditambahkan admin).
            $table->string('type', 20)->default('sekolah')->after('name');
            // Diambil otomatis dari kalender libur nasional (bukan diisi admin).
            $table->boolean('is_auto')->default(false)->after('type');
            // Tanggal belum ditetapkan resmi oleh pemerintah.
            $table->boolean('is_tentative')->default(false)->after('is_auto');
            // Admin memutuskan sekolah tetap masuk pada tanggal ini.
            $table->boolean('is_school_day')->default(false)->after('is_tentative');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_auto', 'is_tentative', 'is_school_day']);
        });
    }
};
