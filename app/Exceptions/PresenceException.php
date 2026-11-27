<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Presensi ditolak. Pesannya ditampilkan apa adanya kepada guru/staf.
 */
class PresenceException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'ditolak')
    {
        parent::__construct($message);
    }
}
