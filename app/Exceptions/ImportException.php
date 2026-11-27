<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * File impor tidak bisa diproses. Pesannya ditampilkan apa adanya kepada admin.
 */
class ImportException extends RuntimeException {}
