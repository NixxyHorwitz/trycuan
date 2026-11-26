<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Angka ringkasan untuk panel admin. */
final class Report
{
    /** @return array{labels: string[], views: int[], valid: int[], other: int[], fee: int[], users: int[]} */
    public static function daily(int $days, ?int $campaignId = null): array
    {
        $keys = [];
        for ($i = $days - 1; $i >= 0; $i--) $keys[] = date('Y-m-d', strtotime("-$i days"));
        $args = [$keys[0], $campaignId === null ? 1 : 0, $campaignId ?? 0];
        $db = Database::pdo();

        $st = $db->prepare("SELECT DATE(k.created_at) AS d, SUM(k.status = 'valid') AS ok, SUM(k.status <> 'valid') AS rest FROM clicks k
            WHERE k.created_at >= ? AND (? = 1 OR k.campaign_id = ?) GROUP BY d");
        $st->execute($args);
        $clicks = $st->fetchAll(\PDO::FETCH_UNIQUE);

        $st = $db->prepare("SELECT DATE(l.created_at) AS d, SUM(l.amount) FROM ledger l LEFT JOIN participations p ON p.id = l.participation_id
            WHERE l.type = 'fee' AND l.created_at >= ? AND (? = 1 OR p.campaign_id = ?) GROUP BY d");
        $st->execute($args);
        $fee = $st->fetchAll(\PDO::FETCH_KEY_PAIR);

        $st = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) FROM users WHERE role = 'freelancer' AND created_at >= ? GROUP BY d");
        $st->execute([$keys[0]]);
        $users = $st->fetchAll(\PDO::FETCH_KEY_PAIR);

        $views = self::viewGrowth($keys, $campaignId);
        $out = ['labels' => [], 'views' => [], 'valid' => [], 'other' => [], 'fee' => [], 'users' => []];
        foreach ($keys as $day) {
            $out['labels'][] = date('d/m', strtotime($day));
            $out['views'][] = $views[$day];
            $out['valid'][] = (int) ($clicks[$day]['ok'] ?? 0);
            $out['other'][] = (int) ($clicks[$day]['rest'] ?? 0);
            $out['fee'][] = (int) ($fee[$day] ?? 0);
            $out['users'][] = (int) ($users[$day] ?? 0);
        }
        return $out;
    }

    public static function totals(): array
    {
        return Database::pdo()->query("SELECT
            (SELECT COALESCE(SUM(total_views), 0) FROM participations) AS views,
            (SELECT COUNT(*) FROM clicks WHERE status = 'valid') AS clicks,
            (SELECT COALESCE(SUM(amount), 0) FROM ledger) AS owed,
            (SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'paid') AS paid,
            (SELECT COALESCE(SUM(CAST(fee_total AS SIGNED) - CAST(fee_credited AS SIGNED)), 0) FROM participations WHERE fee_total > fee_credited AND trust_status <> 'rejected') AS waiting,
            (SELECT COUNT(*) FROM campaigns WHERE status = 'active' AND starts_at <= NOW() AND ends_at > NOW()) AS running")->fetch();
    }

    public static function topAccounts(?int $campaignId = null, int $limit = 5): array
    {
        $st = Database::pdo()->prepare("SELECT p.*, a.username, c.title, l.link_verified_at,
            (SELECT COUNT(*) FROM clicks k WHERE k.short_link_id = l.id AND k.campaign_id = p.campaign_id AND k.status = 'valid') AS clicks,
            (SELECT COUNT(*) FROM submissions s WHERE s.participation_id = p.id) AS posts
            FROM participations p JOIN threads_accounts a ON a.id = p.account_id JOIN campaigns c ON c.id = p.campaign_id
            LEFT JOIN short_links l ON l.account_id = a.id WHERE (? = 1 OR p.campaign_id = ?) ORDER BY p.total_views DESC, p.id LIMIT $limit");
        $st->execute([$campaignId === null ? 1 : 0, $campaignId ?? 0]);
        return $st->fetchAll();
    }

    public static function campaign(int $id): array
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) AS accounts, COALESCE(SUM(total_views), 0) AS views, COALESCE(SUM(fee_total), 0) AS fee, COALESCE(SUM(fee_credited), 0) AS credited,
            (SELECT COUNT(*) FROM submissions s JOIN participations q ON q.id = s.participation_id WHERE q.campaign_id = ?) AS posts,
            (SELECT COUNT(*) FROM submissions s JOIN participations q ON q.id = s.participation_id WHERE q.campaign_id = ? AND s.status = 'pending') AS pending,
            (SELECT COUNT(*) FROM clicks k WHERE k.campaign_id = ? AND k.status = 'valid') AS clicks
            FROM participations WHERE campaign_id = ?");
        $st->execute([$id, $id, $id, $id]);
        return $st->fetch();
    }

    /** Pencarian cepat: freelancer dan campaign. */
    public static function search(string $query): array
    {
        $db = Database::pdo();
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $out = [];
        $st = $db->prepare("SELECT u.id, u.name, u.email, a.username FROM users u LEFT JOIN threads_accounts a ON a.user_id = u.id
            WHERE u.role = 'freelancer' AND (u.name LIKE ? OR u.email LIKE ? OR a.username LIKE ?) ORDER BY u.id DESC LIMIT 6");
        $st->execute([$like, $like, $like]);
        foreach ($st as $r) $out[] = ['group' => 'Freelancer', 'label' => $r['name'], 'note' => $r['username'] ? '@' . $r['username'] : $r['email'], 'url' => "/admin/user/{$r['id']}"];
        $st = $db->prepare('SELECT * FROM campaigns WHERE title LIKE ? ORDER BY id DESC LIMIT 6');
        $st->execute([$like]);
        foreach ($st as $r) $out[] = ['group' => 'Campaign', 'label' => $r['title'], 'note' => Campaign::label(Campaign::state($r)), 'url' => "/admin/campaign/{$r['id']}/ringkasan"];
        return $out;
    }

    /** Pertambahan views per hari dari utas yang disetujui. */
    private static function viewGrowth(array $keys, ?int $campaignId): array
    {
        $st = Database::pdo()->prepare("SELECT v.submission_id AS id, DATE(v.checked_at) AS d, MAX(v.views) AS views FROM view_snapshots v
            JOIN submissions s ON s.id = v.submission_id JOIN participations p ON p.id = s.participation_id
            WHERE s.status = 'approved' AND s.missing_at IS NULL AND (? = 1 OR p.campaign_id = ?) GROUP BY v.submission_id, d ORDER BY d");
        $st->execute([$campaignId === null ? 1 : 0, $campaignId ?? 0]);
        $growth = array_fill_keys($keys, 0);
        $last = [];
        foreach ($st as $r) {
            $views = (int) $r['views'];
            $diff = max(0, $views - ($last[$r['id']] ?? 0));
            if (isset($growth[$r['d']])) $growth[$r['d']] += $diff;
            $last[$r['id']] = max($views, $last[$r['id']] ?? 0);
        }
        return $growth;
    }
}
