<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_scans', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 64)->index();
            // Sumber tap: pembaca jaringan (device_id) atau pembaca USB di PC guru (user_id).
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 20)->index();
            $table->string('message')->nullable();
            $table->dateTime('scanned_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_scans');
    }
};
