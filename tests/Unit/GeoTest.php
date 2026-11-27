<?php

namespace Tests\Unit;

use App\Services\AttendanceService;
use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_haversine_distance(): void
    {
        // Satu derajat lintang ≈ 111,19 km.
        $this->assertEqualsWithDelta(111_195, Geo::distance(0, 0, 1, 0), 1);
        $this->assertSame(0.0, Geo::distance(-6.2, 106.8, -6.2, 106.8));

        // Monas ke Bundaran HI kira-kira 2,2 km.
        $this->assertEqualsWithDelta(2_220, Geo::distance(-6.175392, 106.827153, -6.195020, 106.823070), 60);
    }

    public function test_offset_moves_the_point_by_the_given_meters(): void
    {
        [$lat, $lng] = Geo::offset(-6.2, 106.816666, 60, 80);

        $this->assertEqualsWithDelta(100, Geo::distance(-6.2, 106.816666, $lat, $lng), 0.5);
        $this->assertGreaterThan(-6.2, $lat, 'Utara = lintang bertambah');
        $this->assertGreaterThan(106.816666, $lng, 'Timur = bujur bertambah');
    }

    public function test_distance_is_written_in_meters_or_kilometers(): void
    {
        $this->assertSame('85 m', AttendanceService::formatDistance(85));
        $this->assertSame('1,3 km', AttendanceService::formatDistance(1260));
        $this->assertSame('12 km', AttendanceService::formatDistance(12000));
    }
}
