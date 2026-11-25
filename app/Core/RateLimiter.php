<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    /** Menambah satu hit secara atomik. False bila batas terlewati. */
    public static function hit(string $key, int $max, int $seconds): bool
    {
        $db = Database::pdo();
        $hash = self::key($key);
        $now = time();
        // Urutan kolom penting: hits dihitung sebelum expires_at diganti.
        $db->prepare('INSERT INTO rate_limits (limit_key, hits, expires_at) VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE hits = IF(expires_at <= ?, 1, hits + 1), expires_at = IF(expires_at <= ?, ?, expires_at)')
            ->execute([$hash, $now + $seconds, $now, $now, $now + $seconds]);
        $st = $db->prepare('SELECT hits FROM rate_limits WHERE limit_key = ?');
        $st->execute([$hash]);
        $hits = (int) $st->fetchColumn();
        if (random_int(1, 100) === 1) $db->prepare('DELETE FROM rate_limits WHERE expires_at <= ?')->execute([$now]);
        return $hits <= $max;
    }

    public static function clear(string $key): void
    {
        Database::pdo()->prepare('DELETE FROM rate_limits WHERE limit_key = ?')->execute([self::key($key)]);
    }

    private static function key(string $key): string
    {
        return hash_hmac('sha256', $key, Env::get('APP_KEY'));
    }
}
