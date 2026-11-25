<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ThreadsAccount
{
    public static function forUser(int $userId): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM threads_accounts WHERE user_id = ?');
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }

    public static function forPk(string $pk): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM threads_accounts WHERE threads_pk = ?');
        $st->execute([$pk]);
        return $st->fetch() ?: null;
    }

    public static function pending(int $userId): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM threads_verifications WHERE user_id = ?');
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }
}
