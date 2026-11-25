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

    public static function forParticipation(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM submissions WHERE participation_id = ? ORDER BY id DESC');
        $st->execute([$id]);
        return $st->fetchAll();
    }
}
