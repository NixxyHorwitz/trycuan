<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Announcement
{
    public static function published(int $limit = 30): array
    {
        return Database::pdo()->query("SELECT * FROM announcements WHERE status = 'published' ORDER BY id DESC LIMIT $limit")->fetchAll();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT a.*, u.name FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.id DESC LIMIT 100')->fetchAll();
    }

    public static function unread(array $user): int
    {
        $st = Database::pdo()->prepare("SELECT COUNT(*) FROM announcements WHERE status = 'published' AND id > ?");
        $st->execute([$user['seen_announcement']]);
        return (int) $st->fetchColumn();
    }

    public static function markSeen(int $userId): void
    {
        Database::pdo()->prepare('UPDATE users SET seen_announcement = (SELECT COALESCE(MAX(id), 0) FROM announcements) WHERE id = ?')->execute([$userId]);
    }

    public static function create(string $title, string $body, string $level, int $adminId): int
    {
        Database::pdo()->prepare('INSERT INTO announcements (title, body, level, created_by) VALUES (?, ?, ?, ?)')->execute([$title, $body, $level, $adminId]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function setStatus(int $id, string $status): bool
    {
        $st = Database::pdo()->prepare('UPDATE announcements SET status = ? WHERE id = ?');
        $st->execute([$status, $id]);
        return $st->rowCount() === 1;
    }
}
