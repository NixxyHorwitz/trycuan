<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    // Batas tanpa aktivitas, dalam detik.
    private const IDLE = ['admin' => 7200, 'freelancer' => 86400];
    public const REMEMBER = 30 * 86400;

    private static array|false|null $user = null;

    public static function user(): ?array
    {
        if (self::$user === null) {
            $id = $_SESSION['uid'] ?? null;
            $user = $id ? User::find((int) $id) : null;
            // Dicek tiap request: blokir atau ganti password langsung memutus sesi lain.
            $valid = $user && $user['status'] === 'active' && (int) $user['session_version'] === ($_SESSION['sv'] ?? 0)
                && $user['role'] === Area::role()
                && ($_SESSION['seen'] ?? 0) > time() - (empty($_SESSION['remember']) ? self::IDLE[$user['role']] : self::REMEMBER);
            if ($id && !$valid) unset($_SESSION['uid'], $_SESSION['sv']);
            if ($valid) $_SESSION['seen'] = time();
            self::$user = $valid ? $user : false;
        }
        return self::$user ?: null;
    }

    public static function login(array $user, ?bool $remember = null): void
    {
        $remember ??= !empty($_SESSION['remember']);
        session_regenerate_id(true);
        // "Tetap masuk" hanya untuk freelancer; sesi admin selalu pendek.
        $_SESSION['remember'] = $remember = $remember && $user['role'] === 'freelancer';
        if ($remember) Session::persist(self::REMEMBER);
        unset($_SESSION['csrf'], $_SESSION['device_day']);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['seen'] = $_SESSION['login_at'] = time();
        $_SESSION['sv'] = (int) $user['session_version'];
        self::$user = null;
    }

    /** Halaman yang dituju sebelum diminta masuk. */
    public static function intended(): string
    {
        $to = $_SESSION['to'] ?? '';
        unset($_SESSION['to']);
        return $to !== '' ? $to : Area::home();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = false;
    }
}
