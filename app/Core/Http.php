<?php
declare(strict_types=1);

namespace App\Core;

final class Http
{
    public static function secure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && $https !== 'off') return true;
        return ClientIp::behindCloudflare() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** APP_URL https berarti situs publik: paksa HTTPS dan aktifkan HSTS. */
    public static function enforce(): void
    {
        if (!str_starts_with(Env::get('APP_URL'), 'https://')) return;
        if (self::secure()) {
            header('Strict-Transport-Security: max-age=31536000');
            return;
        }
        // Tujuan dari konfigurasi, bukan header Host milik pengirim.
        header('Location: ' . rtrim(Env::get('APP_URL'), '/') . '/' . ltrim($_SERVER['REQUEST_URI'] ?? '/', '/'), true, 301);
        exit;
    }

    /** Mengirim respons sekarang, lalu melanjutkan pekerjaan lambat tanpa menahan pengunjung. */
    public static function finish(): void
    {
        ignore_user_abort(true);
        session_write_close();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        header('Connection: close');
        header('Content-Length: 0');
        while (ob_get_level()) ob_end_clean();
        flush();
    }
}
