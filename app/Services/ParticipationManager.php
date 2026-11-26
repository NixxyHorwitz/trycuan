<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Setting;

final class ParticipationManager
{
    /** Kecuali mode multi, satu akun hanya boleh punya satu campaign yang berjalan. */
    public function join(array $account, int $campaignId): void
    {
        $this->transaction(function () use ($account, $campaignId): void {
            $db = Database::pdo();
            // Kunci akun supaya dua permintaan bersamaan tidak masuk ke dua campaign.
            $db->prepare('SELECT id FROM threads_accounts WHERE id = ? FOR UPDATE')->execute([$account['id']]);
            $campaign = Campaign::find($campaignId, true);
            if (!$campaign || Campaign::state($campaign) !== 'open') throw new SubmissionException('Campaign ini sedang tidak menerima peserta.');
            if (Participation::find((int) $account['id'], $campaignId)) return;
            if (!Campaign::offered($campaignId)) throw new SubmissionException('Saat ini hanya campaign utama yang bisa diikuti.');
            $current = Setting::str('campaign_mode') === 'multi' ? null : Participation::current((int) $account['id']);
            if ($current) throw new SubmissionException('Akun kamu masih mengikuti campaign ' . $current['title'] . '.');
            $db->prepare('INSERT INTO participations (account_id, campaign_id, rate_snapshot, cap_snapshot) VALUES (?, ?, ?, ?)')
                ->execute([$account['id'], $campaignId, $campaign['rate_per_1k'], $campaign['cap_per_account']]);
        });
    }

    /** Hanya bisa selama belum ada utas yang dikirim. */
    public function leave(array $account, int $campaignId): void
    {
        $this->transaction(function () use ($account, $campaignId): void {
            $db = Database::pdo();
            $st = $db->prepare('SELECT id FROM participations WHERE account_id = ? AND campaign_id = ? FOR UPDATE');
            $st->execute([$account['id'], $campaignId]);
            $id = $st->fetchColumn();
            if ($id === false) return;
            $st = $db->prepare('SELECT 1 FROM submissions WHERE participation_id = ? LIMIT 1');
            $st->execute([$id]);
            if ($st->fetchColumn()) throw new SubmissionException('Tidak bisa keluar karena sudah ada utas yang dikirim.');
            $db->prepare('DELETE FROM participations WHERE id = ?')->execute([$id]);
        });
    }

    private function transaction(callable $run): void
    {
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $run();
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
