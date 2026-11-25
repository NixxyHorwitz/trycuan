<?php
declare(strict_types=1);

namespace App\Core;

final class Validate
{
    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $name));
    }

    public static function name(string $name): ?string
    {
        $length = mb_strlen($name);
        return $length >= 2 && $length <= 80 ? null : 'Nama harus 2 sampai 80 karakter.';
    }

    public static function email(string $email): ?string
    {
        return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? null : 'Masukkan email yang valid.';
    }

    public static function password(string $password): ?string
    {
        if (mb_strlen($password) < 8) return 'Password minimal 8 karakter.';
        // bcrypt hanya membaca 72 byte pertama.
        if (strlen($password) > 72) return 'Password maksimal 72 byte.';
        return str_contains($password, "\0") ? 'Password berisi karakter yang tidak didukung.' : null;
    }
}
