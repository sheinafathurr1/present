<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembaca kartu mandiri (ESP32/ESP8266) yang mengirim lewat API ber-token.
        // Pembaca USB di PC guru tidak perlu didaftarkan di sini.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('location', 150)->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
