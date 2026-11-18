<?php

namespace App\Support;

/**
 * Perhitungan jarak di permukaan bumi.
 */
final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    /** Jarak dua titik (meter), rumus haversine. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Titik yang bergeser sekian meter ke utara & timur (perkiraan, cukup untuk jarak pendek).
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    public static function offset(float $lat, float $lng, float $northMeters, float $eastMeters): array
    {
        $dLat = $northMeters / self::EARTH_RADIUS_M;
        $dLng = $eastMeters / (self::EARTH_RADIUS_M * cos(deg2rad($lat)));

        return [$lat + rad2deg($dLat), $lng + rad2deg($dLng)];
    }
}
