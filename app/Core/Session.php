<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '86400');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path(ROOT . '/storage/sessions');
        session_name('trycuan_session');
        $https = $_SERVER['HTTPS'] ?? '';
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $https !== '' && $https !== 'off',
        ]);
        session_start();
    }

    public static function flash(string $message): void
    {
        $_SESSION['flash'] = $message;
    }

    public static function takeFlash(): ?string
    {
        $message = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $message;
    }
}
