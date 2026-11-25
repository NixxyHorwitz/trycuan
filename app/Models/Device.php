<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\ClientIp;
use App\Core\Database;
use App\Core\Env;

/** Perangkat dikenali lewat cookie first-party acak yang ditandatangani. */
final class Device
{
    private const COOKIE = 'trycuan_device';

    /** Nilai cookie yang sah, atau null. */
    public static function cookie(): ?string
    {
        $value = $_COOKIE[self::COOKIE] ?? '';
        return is_string($value) && self::valid($value) ? $value : null;
    }

    public static function issue(): string
    {
        $value = self::cookie() ?? self::create();
        $https = $_SERVER['HTTPS'] ?? '';
        setcookie(self::COOKIE, $value, [
            'expires' => time() + 365 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => $https !== '' && $https !== 'off',
        ]);
        return $value;
    }

    public static function create(): string
    {
        $id = bin2hex(random_bytes(16));
        return $id . '.' . self::sign($id);
    }

    public static function valid(string $value): bool
    {
        return preg_match('/^([0-9a-f]{32})\.([0-9a-f]{32})$/D', $value, $m) === 1 && hash_equals(self::sign($m[1]), $m[2]);
    }

    public static function touch(string $cookie): int
    {
        $db = Database::pdo();
        $hash = hash('sha256', $cookie);
        $db->prepare('INSERT INTO devices (cookie_hash) VALUES (?) ON DUPLICATE KEY UPDATE last_seen_at = NOW()')->execute([$hash]);
        $st = $db->prepare('SELECT id FROM devices WHERE cookie_hash = ?');
        $st->execute([$hash]);
        return (int) $st->fetchColumn();
    }

    public static function linkUser(int $userId): void
    {
        $id = self::touch(self::issue());
        Database::pdo()->prepare('INSERT INTO user_devices (user_id, device_id, ip_hash, last_login_at) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE ip_hash = VALUES(ip_hash), last_login_at = NOW()')
            ->execute([$userId, $id, ClientIp::hash(ClientIp::current())]);
    }

    private static function sign(string $id): string
    {
        return substr(hash_hmac('sha256', "device:$id", Env::get('APP_KEY')), 0, 32);
    }
}
