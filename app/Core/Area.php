<?php
declare(strict_types=1);

namespace App\Core;

/** Panel admin dan aplikasi freelancer memakai sesi dan cookie yang berbeda. */
final class Area
{
    public static function isAdmin(): bool
    {
        $path = '/' . trim(explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0], '/');
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    public static function role(): string
    {
        return self::isAdmin() ? 'admin' : 'freelancer';
    }

    public static function login(): string
    {
        return self::isAdmin() ? '/admin/masuk' : '/masuk';
    }

    public static function home(): string
    {
        return self::isAdmin() ? '/admin' : '/dashboard';
    }
}
