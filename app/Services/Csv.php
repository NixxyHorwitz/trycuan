<?php
declare(strict_types=1);

namespace App\Services;

final class Csv
{
    public static function send(string $name, array $head, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head, ',', '"', '');
        foreach ($rows as $row) fputcsv($out, array_map(self::safe(...), array_values($row)), ',', '"', '');
        fclose($out);
    }

    /** Isi dari user tidak boleh terbaca sebagai rumus oleh aplikasi spreadsheet. */
    public static function safe(mixed $value): string
    {
        $value = (string) $value;
        return $value !== '' && !is_numeric($value) && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }
}
