<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Click
{
    public static function validCount(int $linkId, int $campaignId): int
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM clicks WHERE short_link_id = ? AND campaign_id = ? AND status = 'valid'");
        $st->execute([$linkId, $campaignId]);
        return (int) $st->fetchColumn();
    }

    /** Klik valid tujuh hari terakhir: tanggal => jumlah. */
    public static function week(int $linkId, int $campaignId): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
        $st = Database::pdo()->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS total FROM clicks
            WHERE short_link_id = ? AND campaign_id = ? AND status = 'valid' AND created_at >= ? GROUP BY day");
        $st->execute([$linkId, $campaignId, array_key_first($days)]);
        foreach ($st as $row) if (isset($days[$row['day']])) $days[$row['day']] = (int) $row['total'];
        return $days;
    }

    public static function recent(int $limit = 100): array
    {
        return Database::pdo()->query('SELECT c.*, a.username, l.code FROM clicks c JOIN short_links l ON l.id = c.short_link_id
            JOIN threads_accounts a ON a.id = l.account_id ORDER BY c.id DESC LIMIT ' . $limit)->fetchAll();
    }

    /** Jejak perangkat dan jaringan dihapus setelah 90 hari; status klik tetap. */
    public static function purge(): void
    {
        $db = Database::pdo();
        $db->exec('DELETE FROM click_tokens WHERE used_at < NOW() - INTERVAL 1 DAY');
        $db->exec('UPDATE clicks SET ip_hash = NULL, subnet_hash = NULL, trait_hash = NULL, device_id = NULL
            WHERE created_at < NOW() - INTERVAL 90 DAY AND ip_hash IS NOT NULL');
        $db->exec('UPDATE user_devices SET ip_hash = NULL WHERE last_login_at < NOW() - INTERVAL 90 DAY AND ip_hash IS NOT NULL');
    }
}
