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

    /** Campaign yang masih mengikat akun: berjalan atau dijeda, dan belum lewat batas akhir. */
    public static function current(int $accountId): ?array
    {
        $st = Database::pdo()->prepare("SELECT c.* FROM participations p JOIN campaigns c ON c.id = p.campaign_id
            WHERE p.account_id = ? AND c.status IN ('active','paused') AND c.ends_at > NOW() LIMIT 1");
        $st->execute([$accountId]);
        return $st->fetch() ?: null;
    }
}
