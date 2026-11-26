<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Audit;
use App\Models\Ledger;
use App\Models\Setting;

/** Memindahkan fee yang lewat masa tahan ke saldo. Selalu lewat persetujuan admin. */
final class FeeCredit
{
    /**
     * Fee yang boleh dikreditkan: levelnya sudah melewati masa tahan, dan semua utas
     * yang dihitung terbaca ulang setelah masa tahan itu selesai.
     *
     * @return array{ready: int, next: ?int} total level siap dan waktu siap level berikutnya
     */
    public static function eligible(array $part): array
    {
        if ($part['trust_status'] === 'rejected') return ['ready' => 0, 'next' => null];
        $db = Database::pdo();
        $st = $db->prepare("SELECT MIN(last_checked_at) FROM submissions WHERE participation_id = ? AND status = 'approved' AND missing_at IS NULL");
        $st->execute([$part['id']]);
        $oldest = $st->fetchColumn();
        $st = $db->prepare('SELECT level, reached_at FROM fee_holds WHERE participation_id = ? AND level <= ? AND level > ? ORDER BY level');
        $st->execute([$part['id'], $part['fee_total'], $part['fee_credited']]);
        $ready = 0;
        $next = null;
        foreach ($st as $hold) {
            $done = strtotime($hold['reached_at']) + Setting::int('hold_days') * 86400;
            if ($done <= time() && $oldest && strtotime($oldest) >= $done) $ready = (int) $hold['level'];
            else $next ??= $done;
        }
        return ['ready' => AccountFeeCalculator::fresh($ready, (int) $part['fee_credited']), 'next' => $next];
    }

    /** $expected adalah nominal yang dilihat admin; bila sudah berubah, batal. Admin null berarti kredit otomatis. */
    public static function approve(int $participationId, int $expected, ?int $adminId): void
    {
        self::locked($participationId, static function (array $part) use ($expected, $adminId): void {
            $ready = self::eligible($part)['ready'];
            if ($ready <= 0) throw new PayoutException('Tidak ada fee yang siap dikreditkan.');
            if ($ready !== $expected) throw new PayoutException('Nominal sudah berubah. Periksa lagi sebelum menyetujui.');
            $total = (int) $part['fee_credited'] + $ready;
            Ledger::add((int) $part['user_id'], 'fee', $ready, "fee:{$part['id']}:$total", (int) $part['id']);
            Database::pdo()->prepare('UPDATE participations SET fee_credited = ? WHERE id = ?')->execute([$total, $part['id']]);
            self::audit($adminId, 'fee.credited', $part, ['amount' => $ready]);
        });
    }

    public static function setTrust(int $participationId, string $status, int $adminId, string $reason): void
    {
        self::locked($participationId, static function (array $part) use ($status, $adminId, $reason): void {
            Database::pdo()->prepare('UPDATE participations SET trust_status = ? WHERE id = ?')->execute([$status, $part['id']]);
            self::audit($adminId, "trust.$status", $part, ['reason' => $reason]);
        });
        AccountFeeCalculator::recalculate($participationId);
    }

    /** Partisipasi yang punya fee di atas kredit, atau ditolak. */
    public static function queue(): array
    {
        $rows = Database::pdo()->query("SELECT p.*, a.username, a.user_id, c.title, l.link_verified_at
            FROM participations p JOIN threads_accounts a ON a.id = p.account_id JOIN campaigns c ON c.id = p.campaign_id
            LEFT JOIN short_links l ON l.account_id = a.id
            WHERE p.fee_total > p.fee_credited OR p.trust_status = 'rejected' ORDER BY p.id LIMIT 200")->fetchAll();
        foreach ($rows as &$row) $row += self::eligible($row) + ['trust' => AccountTrustEvaluator::evaluate($row)];
        unset($row);
        usort($rows, static fn (array $a, array $b): int => $b['ready'] <=> $a['ready']);
        return $rows;
    }

    private static function locked(int $id, callable $run): void
    {
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT * FROM participations WHERE id = ? FOR UPDATE');
            $st->execute([$id]);
            $part = $st->fetch() ?: throw new PayoutException('Partisipasi tidak ditemukan.');
            $st = $db->prepare('SELECT user_id FROM threads_accounts WHERE id = ?');
            $st->execute([$part['account_id']]);
            $part['user_id'] = $st->fetchColumn();
            $run($part);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    private static function audit(?int $adminId, string $action, array $part, array $meta): void
    {
        Audit::add($adminId, $action, "participation:{$part['id']}", $meta);
    }
}
