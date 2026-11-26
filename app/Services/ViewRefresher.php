<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Campaign;
use App\Services\Threads\Outcome;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;

/** Memperbarui views satu utas. Angka lama dipertahankan bila gagal dibaca. */
final class ViewRefresher
{
    public const COOLDOWN = 60;
    public const STALE = 600;
    /** Views masih bisa diperbarui selama ini setelah campaign selesai. */
    public const GRACE = 86400;
    /** Setelah itu utas hanya dicek ulang untuk pencairan; views tidak bisa naik lagi. */
    public const VERIFY = 30 * 86400;

    public function __construct(private readonly ThreadsSource $source)
    {
    }

    public static function allowed(array $campaign): bool
    {
        return $campaign['status'] !== 'draft' && strtotime($campaign['ends_at']) + self::VERIFY > time();
    }

    public static function frozen(array $campaign): bool
    {
        return strtotime($campaign['ends_at']) + self::GRACE <= time();
    }

    /** @return int[] utas yang perlu diperbarui, yang paling lama lebih dulu */
    public static function due(int $accountId, int $olderThan): array
    {
        // Termasuk campaign lama yang masih menunggu pencairan.
        $st = Database::pdo()->prepare("SELECT s.id FROM submissions s JOIN participations p ON p.id = s.participation_id JOIN campaigns c ON c.id = p.campaign_id
            WHERE p.account_id = ? AND s.status <> 'rejected' AND c.ends_at > NOW() - INTERVAL ? SECOND
            AND GREATEST(s.last_checked_at, COALESCE(s.last_attempt_at, s.last_checked_at)) <= NOW() - INTERVAL ? SECOND ORDER BY s.last_checked_at");
        $st->execute([$accountId, self::VERIFY, $olderThan]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return 'ok'|'failed'|'missing'|'wait' */
    public function refresh(array $submission, array $account): string
    {
        $db = Database::pdo();
        $id = (int) $submission['id'];
        // Satu UPDATE atomik: klik ganda dan tab paralel hanya menghasilkan satu request ke Threads.
        $st = $db->prepare('UPDATE submissions SET last_attempt_at = NOW() WHERE id = ?
            AND GREATEST(last_checked_at, COALESCE(last_attempt_at, last_checked_at)) <= NOW() - INTERVAL ? SECOND');
        $st->execute([$id, self::COOLDOWN]);
        if ($st->rowCount() !== 1) return 'wait';

        try {
            $post = $this->source->post($submission['post_code']);
        } catch (ThreadsException $error) {
            if ($error->outcome === Outcome::NotFound) {
                $db->prepare('UPDATE submissions SET missing_at = COALESCE(missing_at, NOW()), last_error = ? WHERE id = ?')->execute([$error->outcome->message(), $id]);
                AccountFeeCalculator::recalculate((int) $submission['participation_id']);
                return 'missing';
            }
            return $this->fail($id, $error->outcome->message());
        }
        if ($post->ownerPk !== $account['threads_pk']) return $this->fail($id, 'Pemilik utas tidak cocok.');
        if ($post->views === null) {
            // Angka yang pernah dibaca dari Threads tidak boleh dianggap tetap bila kini disembunyikan.
            if ($submission['views'] !== null && $submission['view_source'] === 'threads') return $this->fail($id, 'Views tidak ditampilkan Threads. Setor lewat screenshot insight.');
            // Belum ada views, atau angkanya dari insight: cukup pastikan utasnya masih ada.
            $db->prepare('UPDATE submissions SET likes = ?, last_checked_at = NOW(), last_error = NULL, missing_at = NULL WHERE id = ?')->execute([$post->likes, $id]);
            AccountFeeCalculator::recalculate((int) $submission['participation_id']);
            return 'ok';
        }

        $st = $db->prepare('SELECT c.* FROM campaigns c JOIN participations p ON p.campaign_id = c.id WHERE p.id = ?');
        $st->execute([$submission['participation_id']]);
        $views = self::frozen($st->fetch()) ? min($post->views, (int) ($submission['views'] ?? 0)) : $post->views;
        $db->prepare("UPDATE submissions SET views = ?, view_source = 'threads', likes = ?, last_checked_at = NOW(), last_error = NULL, missing_at = NULL WHERE id = ?")
            ->execute([$views, $post->likes, $id]);
        $db->prepare('INSERT INTO view_snapshots (submission_id, views, likes) VALUES (?, ?, ?)')->execute([$id, $views, $post->likes]);
        AccountFeeCalculator::recalculate((int) $submission['participation_id']);
        return 'ok';
    }

    private function fail(int $id, string $message): string
    {
        Database::pdo()->prepare('UPDATE submissions SET last_error = ? WHERE id = ?')->execute([$message, $id]);
        return 'failed';
    }
}
