<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Catatan mutasi. Hanya ditambah, tidak pernah diubah atau dihapus. */
final class Ledger
{
    public static function balance(int $userId): int
    {
        $st = Database::pdo()->prepare('SELECT COALESCE(SUM(amount), 0) FROM ledger WHERE user_id = ?');
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    /** Kunci idempotensi yang sama ditolak database. */
    public static function add(int $userId, string $type, int $amount, string $key, ?int $participationId = null, ?int $withdrawalId = null): void
    {
        Database::pdo()->prepare('INSERT INTO ledger (user_id, type, amount, participation_id, withdrawal_id, idempotency_key) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $type, $amount, $participationId, $withdrawalId, $key]);
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        $st = Database::pdo()->prepare("SELECT l.*, c.title, w.status AS withdrawal_status, w.reason, w.proof_file, w.provider FROM ledger l
            LEFT JOIN participations p ON p.id = l.participation_id LEFT JOIN campaigns c ON c.id = p.campaign_id
            LEFT JOIN withdrawals w ON w.id = l.withdrawal_id WHERE l.user_id = ? ORDER BY l.id DESC LIMIT $limit");
        $st->execute([$userId]);
        return $st->fetchAll();
    }
}
