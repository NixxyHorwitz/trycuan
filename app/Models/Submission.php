<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Submission
{
    public static function exists(string $postId): bool
    {
        $st = Database::pdo()->prepare('SELECT 1 FROM submissions WHERE post_id = ?');
        $st->execute([$postId]);
        return (bool) $st->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM submissions WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function owned(int $id, int $accountId): ?array
    {
        $st = Database::pdo()->prepare('SELECT s.*, p.campaign_id FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE s.id = ? AND p.account_id = ?');
        $st->execute([$id, $accountId]);
        return $st->fetch() ?: null;
    }

    public static function forParticipation(int $id): array
    {
        $st = Database::pdo()->prepare("SELECT s.*, EXISTS(SELECT 1 FROM view_reports r WHERE r.submission_id = s.id AND r.status = 'pending') AS reported FROM submissions s WHERE participation_id = ? ORDER BY id DESC");
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public const PAGE = 20;

    /** Utas akun lintas campaign. @return array{0: array, 1: int} */
    public static function forAccount(int $accountId, string $status, int $campaignId, string $text, int $page): array
    {
        $like = '%' . addcslashes($text, '%_\\') . '%';
        $where = "FROM submissions s JOIN participations p ON p.id = s.participation_id JOIN campaigns c ON c.id = p.campaign_id
            WHERE p.account_id = ? AND (? = '' OR s.status = ? OR (? = 'kosong' AND s.views IS NULL AND s.status <> 'rejected')) AND (? = 0 OR c.id = ?) AND s.excerpt LIKE ?";
        $args = [$accountId, $status, $status, $status, $campaignId, $campaignId, $like];
        $st = Database::pdo()->prepare("SELECT COUNT(*) $where");
        $st->execute($args);
        $total = (int) $st->fetchColumn();
        $offset = ($page - 1) * self::PAGE;
        $st = Database::pdo()->prepare("SELECT s.*, c.id AS campaign_id, c.title, c.status AS campaign_status, c.starts_at, c.ends_at,
            EXISTS(SELECT 1 FROM view_reports r WHERE r.submission_id = s.id AND r.status = 'pending') AS reported $where ORDER BY s.posted_at DESC, s.id DESC LIMIT " . self::PAGE . " OFFSET $offset");
        $st->execute($args);
        return [$st->fetchAll(), $total];
    }

    public static function accountStats(int $accountId): array
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(s.status = 'pending'), 0) AS pending, COALESCE(SUM(s.status = 'approved'), 0) AS approved, COALESCE(SUM(s.status = 'rejected'), 0) AS rejected,
            COALESCE(SUM(s.views IS NULL AND s.status <> 'rejected'), 0) AS blank, COALESCE(SUM(IF(s.status = 'approved' AND s.missing_at IS NULL, s.views, 0)), 0) AS views
            FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE p.account_id = ?");
        $st->execute([$accountId]);
        return array_map('intval', $st->fetch());
    }

    /** Satu status; null berarti yang menunggu lalu 50 keputusan terakhir. */
    public static function forReview(?string $status = null): array
    {
        $select = 'SELECT s.*, a.username, a.user_id, c.title FROM submissions s JOIN participations p ON p.id = s.participation_id
            JOIN threads_accounts a ON a.id = p.account_id JOIN campaigns c ON c.id = p.campaign_id';
        $db = Database::pdo();
        $pending = $status === null || $status === 'pending' ? $db->query("$select WHERE s.status = 'pending' ORDER BY s.id LIMIT 100")->fetchAll() : [];
        if ($status === 'pending') return $pending;
        $st = $db->prepare("$select WHERE s.status <> 'pending' AND (? = '' OR s.status = ?) ORDER BY s.reviewed_at DESC LIMIT 50");
        $st->execute([$status ?? '', $status ?? '']);
        return [...$pending, ...$st->fetchAll()];
    }

    public static function counts(): array
    {
        return Database::pdo()->query('SELECT status, COUNT(*) FROM submissions GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR) + ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    }

    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'")->fetchColumn();
    }

    /** Total views utas yang dihitung pada akhir tiap hari, tujuh hari terakhir. */
    public static function viewsByDay(int $participationId): array
    {
        $st = Database::pdo()->prepare("SELECT v.submission_id, v.views, v.checked_at FROM view_snapshots v JOIN submissions s ON s.id = v.submission_id
            WHERE s.participation_id = ? AND s.status = 'approved' AND s.missing_at IS NULL ORDER BY v.checked_at, v.id");
        $st->execute([$participationId]);
        $rows = $st->fetchAll();
        $latest = $days = [];
        $next = 0;
        for ($i = 6; $i >= 0; $i--) {
            $end = date('Y-m-d 23:59:59', strtotime("-$i days"));
            while ($next < count($rows) && $rows[$next]['checked_at'] <= $end) {
                $latest[$rows[$next]['submission_id']] = (int) $rows[$next]['views'];
                $next++;
            }
            $days[] = array_sum($latest);
        }
        return $days;
    }
}
