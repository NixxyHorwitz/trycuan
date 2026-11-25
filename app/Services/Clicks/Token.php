<?php
declare(strict_types=1);

namespace App\Services\Clicks;

use App\Core\Database;
use App\Core\Env;

/** Token klik bertanda tangan, terikat ke link, campaign, dan cookie perangkat. */
final class Token
{
    public const TTL = 600;

    public static function make(string $code, int $campaignId, string $cookie, ?int $now = null): string
    {
        $body = implode('.', [bin2hex(random_bytes(16)), $campaignId, $now ?? time()]);
        return $body . '.' . self::sign($body, $code, $cookie);
    }

    /** ID campaign bila tanda tangan dan umur sah. Belum menghanguskan token. */
    public static function check(string $token, string $code, string $cookie): ?int
    {
        if (!preg_match('/^([0-9a-f]{32}\.(\d{1,18})\.(\d{1,12}))\.([0-9a-f]{64})$/D', $token, $m)) return null;
        if (!hash_equals(self::sign($m[1], $code, $cookie), $m[4])) return null;
        $age = time() - (int) $m[3];
        return $age >= 0 && $age <= self::TTL ? (int) $m[2] : null;
    }

    /** False bila token sudah pernah dipakai. */
    public static function consume(string $token): bool
    {
        $st = Database::pdo()->prepare('INSERT IGNORE INTO click_tokens (nonce) VALUES (?)');
        $st->execute([substr($token, 0, 32)]);
        return $st->rowCount() === 1;
    }

    private static function sign(string $body, string $code, string $cookie): string
    {
        return hash_hmac('sha256', "click|$body|$code|$cookie", Env::get('APP_KEY'));
    }
}
