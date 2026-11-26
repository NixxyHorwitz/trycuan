<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/** Mengkreditkan akun berstatus wajar tanpa admin, bila diaktifkan di pengaturan. */
final class AutoCredit
{
    public static function run(int $participationId): bool
    {
        if (!Setting::int('auto_credit')) return false;
        $st = Database::pdo()->prepare('SELECT * FROM participations WHERE id = ?');
        $st->execute([$participationId]);
        $part = $st->fetch();
        if (!$part) return false;
        $ready = FeeCredit::eligible($part)['ready'];
        if ($ready <= 0 || AccountTrustEvaluator::evaluate($part)['status'] !== 'wajar') return false;
        try {
            FeeCredit::approve($participationId, $ready, null);
        } catch (PayoutException) {
            return false;
        }
        return true;
    }
}
