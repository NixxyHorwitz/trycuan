<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Participation
{
    public static function find(int $accountId, int $campaignId): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM participations WHERE account_id = ? AND campaign_id = ?');
        $st->execute([$accountId, $campaignId]);
        return $st->fetch() ?: null;
    }

    /** Yang masih mengikat akun lebih dulu, lalu yang terakhir diikuti. */
    public static function latest(int $accountId): ?array
    {
        $st = Database::pdo()->prepare("SELECT p.* FROM participations p JOIN campaigns c ON c.id = p.campaign_id
            WHERE p.account_id = ? ORDER BY (c.status IN ('active','paused') AND c.ends_at > NOW()) DESC, p.id DESC LIMIT 1");
        $st->execute([$accountId]);
        return $st->fetch() ?: null;
    }

    /** Campaign yang masih mengikat akun: berjalan atau dijeda, dan belum lewat batas akhir. */
    public static function current(int $accountId): ?array
    {
        return self::running($accountId)[0] ?? null;
    }

    /** @return list<array> semua campaign yang masih mengikat, terbaru lebih dulu */
    public static function running(int $accountId): array
    {
        $st = Database::pdo()->prepare("SELECT c.* FROM participations p JOIN campaigns c ON c.id = p.campaign_id
            WHERE p.account_id = ? AND c.status IN ('active','paused') AND c.ends_at > NOW() ORDER BY p.id DESC");
        $st->execute([$accountId]);
        return $st->fetchAll();
    }

    public static function byId(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT p.*, a.username, a.user_id FROM participations p JOIN threads_accounts a ON a.id = p.account_id WHERE p.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function forAccount(int $accountId): array
    {
        $st = Database::pdo()->prepare('SELECT p.*, c.title FROM participations p JOIN campaigns c ON c.id = p.campaign_id WHERE p.account_id = ? ORDER BY p.id DESC');
        $st->execute([$accountId]);
        return $st->fetchAll();
    }
}
