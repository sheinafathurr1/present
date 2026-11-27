<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Pembantu menulis Excel/CSV dengan aman.
 *
 * Teks yang diawali "=" (atau +, -, @ di CSV) bisa dijalankan Excel sebagai rumus.
 * Karena sebagian teks diketik pengguna (nama, alasan izin), teks selalu ditulis
 * sebagai teks biasa, tidak pernah sebagai rumus.
 */
final class Spreadsheet
{
    /** Untuk CSV: beri tanda petik di depan teks yang bisa dibaca sebagai rumus. */
    public static function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** Satu sel Excel. Teks selalu menjadi sel teks (bukan rumus). */
    public static function cell(mixed $value, ?Style $style = null): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null, $style);
        }

        if (is_string($value)) {
            return new StringCell($value, $style);
        }

        return Cell::fromValue($value instanceof DateTimeInterface || is_scalar($value) ? $value : (string) $value, $style);
    }

    /**
     * @param  list<mixed>  $values
     * @param  array<int, Style>  $styles  gaya per kolom (opsional)
     */
    public static function row(array $values, ?Style $style = null, array $styles = [], ?float $height = null): Row
    {
        $cells = [];
        foreach (array_values($values) as $index => $value) {
            $cells[] = self::cell($value, $styles[$index] ?? $style);
        }

        return $height === null ? new Row($cells) : new Row($cells, $height);
    }
}
