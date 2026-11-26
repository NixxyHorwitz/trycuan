<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) Auth::REMEMBER);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path(ROOT . '/storage/sessions');
        $admin = Area::isAdmin();
        session_name($admin ? 'trycuan_admin' : 'trycuan_session');
        session_set_cookie_params([
            'path' => $admin ? '/admin' : '/',
            'httponly' => true,
            'samesite' => $admin ? 'Strict' : 'Lax',
            'secure' => Http::secure(),
        ]);
        session_start();
    }

    /** Cookie sesi bertahan setelah browser ditutup. */
    public static function persist(int $seconds): void
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), ['expires' => time() + $seconds, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => $params['samesite']]);
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
