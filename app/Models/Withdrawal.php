<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Withdrawal
{
    public static function find(int $id, bool $lock = false): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM withdrawals WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function pendingFor(int $userId): ?array
    {
        $st = Database::pdo()->prepare("SELECT * FROM withdrawals WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }

    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();
    }

    /** Status kosong berarti semua: yang menunggu dulu, lalu yang terakhir diproses. */
    public static function forAdmin(string $status = '', int $limit = 100): array
    {
        $st = Database::pdo()->prepare("SELECT w.*, u.name, u.status AS user_status, a.username,
            (SELECT COUNT(DISTINCT m.user_id) FROM payout_methods m WHERE m.account_hash = w.account_hash AND m.user_id <> w.user_id) +
            (SELECT COUNT(DISTINCT x.user_id) FROM withdrawals x WHERE x.account_hash = w.account_hash AND x.user_id <> w.user_id) AS shared
            FROM withdrawals w JOIN users u ON u.id = w.user_id LEFT JOIN threads_accounts a ON a.user_id = u.id
            WHERE (? = '' OR w.status = ?) ORDER BY w.status = 'pending' DESC, CASE WHEN w.status = 'pending' THEN w.id END, w.processed_at DESC LIMIT $limit");
        $st->execute([$status, $status]);
        return $st->fetchAll();
    }

    public static function counts(): array
    {
        return Database::pdo()->query('SELECT status, COUNT(*) FROM withdrawals GROUP BY status')->fetchAll(\PDO::FETCH_KEY_PAIR) + ['pending' => 0, 'paid' => 0, 'rejected' => 0];
    }
}
