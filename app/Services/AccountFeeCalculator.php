<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Campaign;

/** Fee dihitung dari jumlah views semua utas akun, baru dibulatkan. */
final class AccountFeeCalculator
{
    public const STEP = 1000;

    public static function total(int $views, int $rate, int $cap): int
    {
        $steps = intdiv(max(0, $views), self::STEP);
        // Dibatasi sebelum dikali supaya tidak melewati batas integer.
        return min(min($steps, intdiv($cap, $rate) + 1) * $rate, $cap);
    }

    /** Hanya selisih di atas kredit; views turun tidak menagih balik. */
    public static function fresh(int $total, int $credited): int
    {
        return max(0, $total - $credited);
    }

    /** Menghitung ulang total views dan estimasi fee satu partisipasi. */
    public static function recalculate(int $participationId): void
    {
        $db = Database::pdo();
        // campaign_id tidak pernah berubah. Dibaca di luar transaksi agar snapshot baru dibuat setelah kunci didapat.
        $st = $db->prepare('SELECT campaign_id FROM participations WHERE id = ?');
        $st->execute([$participationId]);
        $campaignId = $st->fetchColumn();
        if ($campaignId === false) return;
        $db->beginTransaction();
        try {
            $campaign = Campaign::find((int) $campaignId, true);
            $st = $db->prepare('SELECT * FROM participations WHERE id = ? FOR UPDATE');
            $st->execute([$participationId]);
            $part = $st->fetch();

            $st = $db->prepare("SELECT COALESCE(SUM(views), 0) FROM submissions WHERE participation_id = ? AND status = 'approved' AND missing_at IS NULL");
            $st->execute([$participationId]);
            $views = (int) $st->fetchColumn();

            $rate = (int) $part['rate_snapshot'];
            $fee = self::total($views, $rate, (int) $part['cap_snapshot']);
            // Anggaran: estimasi akun lain sudah dipesan lebih dulu.
            $st = $db->prepare('SELECT COALESCE(SUM(GREATEST(fee_total, fee_credited)), 0) FROM participations WHERE campaign_id = ? AND id <> ?');
            $st->execute([$campaignId, $participationId]);
            $left = max(0, (int) $campaign['budget'] - (int) $st->fetchColumn());
            if ($fee > $left) $fee = intdiv($left, $rate) * $rate;

            // Partisipasi yang ditolak melepas anggaran di atas yang sudah cair.
            if ($part['trust_status'] === 'rejected') $fee = min($fee, (int) $part['fee_credited']);

            // Masa tahan tiap level dimulai saat level pertama tercapai; turun berarti mulai ulang.
            $db->prepare('DELETE FROM fee_holds WHERE participation_id = ? AND level > ?')->execute([$participationId, $fee]);
            $st = $db->prepare('SELECT COALESCE(MAX(level), 0) FROM fee_holds WHERE participation_id = ?');
            $st->execute([$participationId]);
            if ($fee > (int) $st->fetchColumn()) {
                $db->prepare('INSERT INTO fee_holds (participation_id, level, reached_at) VALUES (?, ?, NOW())')->execute([$participationId, $fee]);
            }
            $db->prepare('UPDATE participations SET total_views = ?, fee_total = ? WHERE id = ?')->execute([$views, $fee, $participationId]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
