<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\ClientIp;
use App\Core\RateLimiter;
use App\Models\Audit;
use App\Models\User;

final class Login
{
    // Hash acak: menyamakan waktu respons saat email tidak terdaftar.
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuO9Rk0R4oX3dP4sT2l6o4lq2JdC1r8bK';

    /** Akun dengan peran lain diperlakukan seperti email tidak terdaftar. @return array{0: ?string, 1: int} */
    public static function attempt(string $email, string $password, string $role, bool $remember = false): array
    {
        $ip = ClientIp::current();
        $prefix = $role === 'admin' ? 'admin-login' : 'login';
        $pairKey = "$prefix:$ip:$email";
        // Batas per pasangan IP+email, agar orang lain tidak bisa mengunci akun dari jauh.
        $allowed = RateLimiter::hit("$prefix-ip:$ip", 60, 900);
        $allowed = RateLimiter::hit($pairKey, 5, 900) && $allowed;
        if (!$allowed) return ['Terlalu banyak percobaan. Coba lagi dalam 15 menit.', 429];

        $user = User::findByEmail($email);
        if ($user && $user['role'] !== $role) $user = null;
        $match = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        if (!$user || !$match) {
            $left = RateLimiter::left($pairKey, 5);
            return ['Email atau password salah.' . ($left <= 2 ? ($left ? " Sisa $left percobaan sebelum dikunci 15 menit." : ' Percobaan berikutnya dikunci 15 menit.') : ''), 422];
        }
        if ($user['status'] !== 'active') return [$role === 'admin' ? 'Akun ini nonaktif.' : 'Akun ini diblokir. Hubungi admin.', 422];

        RateLimiter::clear($pairKey);
        Auth::login($user, $remember);
        User::touchLogin((int) $user['id']);
        if ($role === 'admin') Audit::add((int) $user['id'], 'admin.login', "user:{$user['id']}");
        return [null, 200];
    }
}
