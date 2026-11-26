<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Audit
{
    public static function add(?int $actorId, string $action, string $target, array $meta = []): void
    {
        Database::pdo()->prepare('INSERT INTO audit_logs (actor_id, action, target, meta_json) VALUES (?, ?, ?, ?)')
            ->execute([$actorId, $action, $target, json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /** Catatan sebelum ID tertentu, bisa dibatasi per kelompok tindakan (awalan sebelum titik). */
    public static function page(int $before, string $group = '', int $limit = 50): array
    {
        $st = Database::pdo()->prepare("SELECT l.*, u.name FROM audit_logs l LEFT JOIN users u ON u.id = l.actor_id
            WHERE l.id < ? AND (? = '' OR l.action LIKE ?) ORDER BY l.id DESC LIMIT $limit");
        $st->execute([$before, $group, addcslashes($group, '%_') . '.%']);
        return $st->fetchAll();
    }

    public static function groups(): array
    {
        return Database::pdo()->query("SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) AS g FROM audit_logs ORDER BY g")->fetchAll(\PDO::FETCH_COLUMN);
    }
}
