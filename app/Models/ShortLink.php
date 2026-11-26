<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Env;

final class ShortLink
{
    public const LENGTH = 7;
    private const LOWER = 'abcdefghijkmnopqrstuvwxyz';
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGIT = '23456789';

    /** Satu link tetap per akun; dibuat saat pertama diminta. */
    public static function forAccount(int $accountId): array
    {
        for ($try = 0; $try < 5; $try++) {
            $st = Database::pdo()->prepare('SELECT * FROM short_links WHERE account_id = ?');
            $st->execute([$accountId]);
            if ($row = $st->fetch()) return $row;
            Database::pdo()->prepare('INSERT IGNORE INTO short_links (account_id, code) VALUES (?, ?)')->execute([$accountId, self::code()]);
        }
        throw new \RuntimeException('Gagal membuat shortlink');
    }

    /** Link beserta pemilik dan campaign terbukanya. Null bila link tidak ada atau user diblokir. */
    public static function resolve(string $code, ?int $campaignId = null): ?array
    {
        $st = Database::pdo()->prepare("SELECT l.*, a.user_id FROM short_links l JOIN threads_accounts a ON a.id = l.account_id
            JOIN users u ON u.id = a.user_id WHERE l.code = ? AND u.status = 'active'");
        $st->execute([$code]);
        $link = $st->fetch();
        if (!$link) return null;
        $link['campaigns'] = array_values(array_filter(Participation::running((int) $link['account_id']), static fn (array $c): bool => Campaign::state($c) === 'open'));
        // Beberapa campaign: pengunjung memilih, jadi klik tercatat di campaign yang tepat.
        $chosen = $campaignId === null ? $link['campaigns'] : array_values(array_filter($link['campaigns'], static fn (array $c): bool => (int) $c['id'] === $campaignId));
        $link['campaign'] = count($chosen) === 1 ? $chosen[0] : null;
        return $link;
    }

    public static function url(array $link): string
    {
        return self::host() . '/' . $link['code'];
    }

    public static function host(): string
    {
        return Env::get('SHORT_HOST', 'trycuan.online');
    }

    public static function markSeen(int $id): void
    {
        Database::pdo()->prepare('UPDATE short_links SET link_verified_at = COALESCE(link_verified_at, NOW()), last_seen_at = NOW(), lost_at = NULL WHERE id = ?')->execute([$id]);
    }

    /** Waktu pasang dihapus: pemasangan berikutnya dihitung dari awal. */
    public static function markLost(int $id): void
    {
        Database::pdo()->prepare('UPDATE short_links SET lost_at = IF(link_verified_at IS NULL, lost_at, NOW()), link_verified_at = NULL WHERE id = ?')->execute([$id]);
    }

    /** Selalu memuat huruf besar dan angka, sehingga tidak mungkin sama dengan route aplikasi. */
    private static function code(): string
    {
        $all = self::LOWER . self::UPPER . self::DIGIT;
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) $code .= $all[random_int(0, strlen($all) - 1)];
        } while (!preg_match('/[A-Z]/', $code) || !preg_match('/\d/', $code));
        return $code;
    }
}
