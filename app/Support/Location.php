<?php

namespace App\Support;

/**
 * Lokasi yang dikirim HP saat presensi.
 */
final readonly class Location
{
    public function __construct(
        public float $lat,
        public float $lng,
        /** Perkiraan akurasi dari HP (meter). */
        public float $accuracy,
    ) {}

    /** @param  array{lat: mixed, lng: mixed, accuracy: mixed}  $data */
    public static function fromArray(array $data): self
    {
        return new self((float) $data['lat'], (float) $data['lng'], (float) $data['accuracy']);
    }
}
