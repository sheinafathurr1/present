<?php

namespace Tests;

use App\Support\Geo;
use App\Support\SchoolSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** Titik sekolah contoh di tes. */
    protected const LAT = -6.2;

    protected const LNG = 106.816666;

    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh memakai internet; tiap permintaan keluar harus dipalsukan.
        Http::preventStrayRequests();
    }

    /** Atur titik & radius sekolah (bawaan: radius 100 m, akurasi maks. 100 m). */
    protected function setUpSchool(array $overrides = []): SchoolSettings
    {
        $settings = app(SchoolSettings::class);
        $settings->update([
            'school_lat' => self::LAT,
            'school_lng' => self::LNG,
            'radius_m' => 100,
            'max_accuracy_m' => 100,
            ...$overrides,
        ]);

        return $settings;
    }

    /**
     * Isian lokasi yang dikirim HP: sekian meter di utara titik sekolah.
     *
     * @return array{lat: float, lng: float, accuracy: int}
     */
    protected function gps(float $metersFromSchool = 20, int $accuracy = 15): array
    {
        [$lat, $lng] = Geo::offset(self::LAT, self::LNG, $metersFromSchool, 0);

        return ['lat' => round($lat, 7), 'lng' => round($lng, 7), 'accuracy' => $accuracy];
    }
}
