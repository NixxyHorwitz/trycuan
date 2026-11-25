<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(): void
    {
        $sent = post('_csrf') ?: (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals(self::token(), $sent)) {
            throw new HttpException(403, 'Sesi formulir sudah berakhir. Muat ulang halaman lalu coba lagi.');
        }
    }
}
