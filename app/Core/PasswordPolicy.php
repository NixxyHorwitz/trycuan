<?php
declare(strict_types=1);

namespace App\Core;

/** Aturan password. Aturan yang sama ditampilkan langsung oleh resources/validate.js, tetapi keputusan selalu di sini. */
final class PasswordPolicy
{
    public const MIN = 10;
    public const ADMIN_MIN = 12;

    /** @param string[] $personal nama, email, atau username yang tidak boleh dimuat */
    public static function error(string $password, array $personal = [], int $min = self::MIN): ?string
    {
        if (mb_strlen($password) < $min) return "Password minimal $min karakter.";
        // bcrypt hanya membaca 72 byte pertama.
        if (strlen($password) > 72) return 'Password maksimal 72 byte.';
        if (str_contains($password, "\0")) return 'Password berisi karakter yang tidak didukung.';
        if (mb_strlen($password) < 16 && self::classes($password) < 3) return 'Gabungkan minimal tiga: huruf kecil, huruf besar, angka, simbol. Atau pakai 16 karakter.';
        if (self::hasPattern($password)) return 'Hindari pola seperti 12345, abcde, qwerty, atau aaaa.';
        if (self::hasPersonal($password, $personal)) return 'Password jangan memuat nama atau email kamu.';
        return self::isCommon($password) ? 'Password ini terlalu umum dan mudah ditebak.' : null;
    }

    private static function classes(string $password): int
    {
        $total = 0;
        foreach (['/\p{Ll}/u', '/\p{Lu}/u', '/\d/', '/[^\p{L}\d]/u'] as $pattern) $total += preg_match($pattern, $password) === 1 ? 1 : 0;
        return $total;
    }

    private static function hasPattern(string $password): bool
    {
        $text = mb_strtolower($password);
        if (preg_match('/(.)\1{3}/u', $text)) return true;
        foreach (['01234567890', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'] as $row) {
            foreach ([$row, strrev($row)] as $line) {
                for ($i = 0; $i + 5 <= strlen($line); $i++) if (str_contains($text, substr($line, $i, 5))) return true;
            }
        }
        return false;
    }

    private static function hasPersonal(string $password, array $personal): bool
    {
        $text = mb_strtolower($password);
        foreach ($personal as $value) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(explode('@', $value)[0])) ?: [] as $part) {
                if (mb_strlen($part) >= 4 && str_contains($text, $part)) return true;
            }
        }
        return false;
    }

    private static function isCommon(string $password): bool
    {
        static $list = null;
        $list ??= array_flip(file(ROOT . '/app/common-passwords.txt', FILE_IGNORE_NEW_LINES) ?: []);
        $text = mb_strtolower($password);
        // password2024! tetap dihitung sebagai "password".
        $bare = (string) preg_replace('/^[^\p{L}]+|[^\p{L}]+$/u', '', $text);
        return isset($list[$text]) || ($bare !== '' && mb_strlen($password) - mb_strlen($bare) <= 6 && isset($list[$bare]));
    }
}
