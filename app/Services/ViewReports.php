<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Audit;
use App\Models\Campaign;
use App\Models\Setting;
use App\Models\ViewReport;
use App\Services\Threads\Identifier;

/** Views yang disetor lewat screenshot insight. Baru dihitung setelah disetujui admin. */
final class ViewReports
{
    public const MAX_VIEWS = 100_000_000;
    public const MAX_FILE = 5 * 1024 * 1024;

    public static function open(array $campaign): bool
    {
        return Setting::int('insight_open') === 1 && $campaign['status'] !== 'draft' && strtotime($campaign['starts_at']) <= time() && !ViewRefresher::frozen($campaign);
    }

    /** @param array $file entri $_FILES */
    public static function submit(array $part, string $code, int $views, array $file): void
    {
        $db = Database::pdo();
        $st = $db->prepare('SELECT * FROM submissions WHERE participation_id = ? AND post_id = ?');
        $st->execute([$part['id'], Identifier::postId($code)]);
        $post = $st->fetch() ?: throw new SubmissionException('Utas ini belum kamu kirim di campaign ini. Kirim utasnya dulu.');
        self::check($post, $views);
        try {
            $name = ProofFile::store($file, 'screenshot insight', self::MAX_FILE, false);
        } catch (PayoutException $error) {
            throw new SubmissionException($error->getMessage(), 'bukti');
        }
        $db->beginTransaction();
        try {
            // Dikunci: kiriman ganda dan keputusan admin tidak saling mendahului.
            $st = $db->prepare('SELECT * FROM submissions WHERE id = ? FOR UPDATE');
            $st->execute([$post['id']]);
            self::check($st->fetch(), $views);
            if (!self::open(Campaign::find((int) $part['campaign_id']))) throw new SubmissionException('Masa setor views campaign ini sudah berakhir.');
            $st = $db->prepare("SELECT 1 FROM view_reports WHERE submission_id = ? AND status = 'pending'");
            $st->execute([$post['id']]);
            if ($st->fetchColumn()) throw new SubmissionException('Setoran utas ini masih ditinjau admin.');
            $db->prepare('INSERT INTO view_reports (submission_id, claimed_views, proof_file, proof_hash) VALUES (?, ?, ?, ?)')
                ->execute([$post['id'], $views, $name, hash_file('sha256', ProofFile::path($name))]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            ProofFile::delete($name);
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? 0) === 1062) {
                throw new SubmissionException('Screenshot ini sudah pernah dikirim. Ambil screenshot baru.', 'bukti');
            }
            throw $error;
        }
    }

    /** Angka dari admin yang dipakai; hasil baca Threads yang lebih besar tidak diturunkan. */
    public static function approve(int $id, int $views, int $adminId): void
    {
        self::decide($id, static function (array $report, array $post) use ($views, $adminId): void {
            if ($report['status'] !== 'pending') throw new SubmissionException('Setoran ini sudah ditinjau.');
            if ($post['status'] === 'rejected') throw new SubmissionException('Utasnya sudah ditolak, jadi views tidak bisa disetujui.');
            if ($views < 1 || $views > self::MAX_VIEWS) throw new SubmissionException('Isi views antara 1 dan ' . angka(self::MAX_VIEWS) . '.');
            $db = Database::pdo();
            if ($views > (int) $post['views']) {
                $db->prepare("UPDATE submissions SET views = ?, view_source = 'insight', last_error = IF(missing_at IS NULL, NULL, last_error) WHERE id = ?")->execute([$views, $post['id']]);
                $db->prepare('INSERT INTO view_snapshots (submission_id, views, likes, report_id) VALUES (?, ?, ?, ?)')->execute([$post['id'], $views, $post['likes'], $report['id']]);
            }
            self::save($report, 'approved', $views, null, $adminId);
        });
    }

    /** Menolak setoran. Bila sebelumnya disetujui, views kembali ke angka lain yang terakhir tercatat. */
    public static function reject(int $id, string $reason, int $adminId): void
    {
        self::decide($id, static function (array $report, array $post) use ($reason, $adminId): void {
            if ($report['status'] === 'rejected') throw new SubmissionException('Setoran ini sudah ditolak.');
            if ($report['status'] === 'approved') {
                $db = Database::pdo();
                $db->prepare('DELETE FROM view_snapshots WHERE report_id = ?')->execute([$report['id']]);
                $st = $db->prepare('SELECT views, report_id FROM view_snapshots WHERE submission_id = ? ORDER BY id DESC LIMIT 1');
                $st->execute([$post['id']]);
                $last = $st->fetch() ?: ['views' => null, 'report_id' => null];
                $db->prepare('UPDATE submissions SET views = ?, view_source = ? WHERE id = ?')->execute([$last['views'], $last['report_id'] ? 'insight' : 'threads', $post['id']]);
            }
            self::save($report, 'rejected', null, $reason, $adminId);
        });
    }

    private static function check(array $post, int $views): void
    {
        if ($post['status'] === 'rejected') throw new SubmissionException('Utas ini ditolak, jadi views-nya tidak dihitung.');
        if ($views < 1 || $views > self::MAX_VIEWS) throw new SubmissionException('Isi jumlah views dari insight.', 'views');
        if ($views <= (int) $post['views']) throw new SubmissionException('Views tercatat sudah ' . angka($post['views']) . '. Setor bila angkanya lebih besar.', 'views');
    }

    private static function decide(int $id, callable $run): void
    {
        $db = Database::pdo();
        $report = ViewReport::find($id) ?? throw new SubmissionException('Setoran tidak ditemukan.');
        $db->beginTransaction();
        try {
            // Urutan kunci sama dengan submit: utas dulu, baru setoran.
            $st = $db->prepare('SELECT * FROM submissions WHERE id = ? FOR UPDATE');
            $st->execute([$report['submission_id']]);
            $post = $st->fetch();
            $run(ViewReport::find($id, true), $post);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        AccountFeeCalculator::recalculate((int) $post['participation_id']);
    }

    private static function save(array $report, string $status, ?int $views, ?string $reason, int $adminId): void
    {
        Database::pdo()->prepare('UPDATE view_reports SET status = ?, approved_views = ?, reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$status, $views, $reason, $adminId, $report['id']]);
        Audit::add($adminId, "insight.$status", "insight:{$report['id']}", ['before' => $report['status'], 'claimed' => (int) $report['claimed_views'], 'views' => $views, 'reason' => $reason]);
    }
}
