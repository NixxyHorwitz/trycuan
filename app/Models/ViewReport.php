<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ViewReport
{
    public static function find(int $id, bool $lock = false): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM view_reports WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Laporan milik user, untuk melihat screenshot sendiri. */
    public static function owned(int $id, int $userId): ?array
    {
        $st = Database::pdo()->prepare('SELECT r.* FROM view_reports r JOIN submissions s ON s.id = r.submission_id JOIN participations p ON p.id = s.participation_id
            JOIN threads_accounts a ON a.id = p.account_id WHERE r.id = ? AND a.user_id = ?');
        $st->execute([$id, $userId]);
        return $st->fetch() ?: null;
    }

    public static function forParticipation(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT r.*, s.excerpt, s.post_code FROM view_reports r JOIN submissions s ON s.id = r.submission_id WHERE s.participation_id = ? ORDER BY r.id DESC LIMIT 30');
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function forReview(string $status): array
    {
        $st = Database::pdo()->prepare("SELECT r.*, s.excerpt, s.post_code, s.views, s.view_source, s.status AS post_status, s.missing_at, a.username, a.user_id, c.title, p.id AS participation_id
            FROM view_reports r JOIN submissions s ON s.id = r.submission_id JOIN participations p ON p.id = s.participation_id
            JOIN threads_accounts a ON a.id = p.account_id JOIN campaigns c ON c.id = p.campaign_id
            WHERE ? = '' OR r.status = ? ORDER BY r.status = 'pending' DESC, IF(r.status = 'pending', CAST(r.id AS SIGNED), -CAST(r.id AS SIGNED)) LIMIT 100");
        $st->execute([$status, $status]);
        return $st->fetchAll();
    }

    public static function counts(): array
    {
        return Database::pdo()->query('SELECT status, COUNT(*) FROM view_reports GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR) + ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    }

    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM view_reports WHERE status = 'pending'")->fetchColumn();
    }
}
