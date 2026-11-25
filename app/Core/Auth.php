<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static array|false|null $user = null;

    public static function user(): ?array
    {
        if (self::$user === null) {
            $id = $_SESSION['uid'] ?? null;
            $user = $id ? User::find((int) $id) : null;
            // Dicek tiap request: blokir atau ganti password langsung memutus sesi lain.
            $valid = $user && $user['status'] === 'active' && (int) $user['session_version'] === ($_SESSION['sv'] ?? 0);
            if ($id && !$valid) unset($_SESSION['uid'], $_SESSION['sv']);
            self::$user = $valid ? $user : false;
        }
        return self::$user ?: null;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrf'], $_SESSION['device_day']);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['sv'] = (int) $user['session_version'];
        self::$user = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = false;
    }
}
