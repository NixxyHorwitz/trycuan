<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;
use App\Services\Threads\Identifier;
use App\Services\Threads\PostLink;
use App\Services\Threads\ThreadsSource;

final class SubmissionVerifier
{
    public function __construct(private readonly ThreadsSource $source, private readonly PostLink $links = new PostLink())
    {
    }

    /** @throws SubmissionException|VerificationException|\App\Services\Threads\ThreadsException */
    public function submit(array $account, int $campaignId, string $url): void
    {
        $campaign = Campaign::find($campaignId);
        $part = Participation::find((int) $account['id'], $campaignId);
        if (!$campaign || !$part) throw new SubmissionException('Ikuti campaign ini dulu.');
        $this->ensureOpen($campaign);
        // Link bagikan butuh satu request, jadi setelah syarat yang bisa dicek lokal.
        $code = $this->links->code($url) ?? throw new SubmissionException(PostLink::HELP);
        $postId = Identifier::postId($code);
        if (Submission::exists($postId)) throw new SubmissionException('Utas ini sudah pernah dikirim.');

        $link = ShortLink::forAccount((int) $account['id']);
        if ($link['link_verified_at'] === null) throw new SubmissionException('Pasang dan cek link di profil Threads dulu.');

        $post = $this->source->post($code);
        if ($post->ownerPk !== $account['threads_pk']) {
            throw new SubmissionException("Utas ini milik @{$post->ownerUsername}, bukan akun terverifikasi kamu.");
        }
        if ($post->isReply) throw new SubmissionException('Kirim post utama, bukan balasan.');

        $this->ensureAfterLink($post->postedAt, $link);
        // Link harus masih terpasang saat utas dikirim.
        (new LinkVerifier($this->source))->check($account);

        $db = Database::pdo();
        $db->beginTransaction();
        try {
            // Dikunci dan dibaca ulang: campaign atau keikutsertaan bisa berubah selama utas diambil.
            $campaign = Campaign::find($campaignId, true);
            $this->ensureOpen($campaign);
            $this->ensureInPeriod($post->postedAt, $campaign);
            // Link bisa dilepas lalu dipasang lagi selama pengecekan.
            $this->ensureAfterLink($post->postedAt, ShortLink::forAccount((int) $account['id']));
            $broken = PostRules::violations($post, Campaign::rules($campaign));
            if ($broken) throw new SubmissionException('Syarat belum terpenuhi. ' . implode(' ', $broken));
            $st = $db->prepare('SELECT id FROM participations WHERE id = ? FOR UPDATE');
            $st->execute([$part['id']]);
            if (!$st->fetchColumn()) throw new SubmissionException('Kamu sudah keluar dari campaign ini.');

            $db->prepare('INSERT INTO submissions (participation_id, post_id, post_code, excerpt, posted_at, views, likes, last_checked_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')->execute([
                $part['id'], $postId, $code, mb_substr(trim($post->text), 0, 300),
                date('Y-m-d H:i:s', $post->postedAt), $post->views, $post->likes,
            ]);
            if ($post->views !== null) {
                $db->prepare('INSERT INTO view_snapshots (submission_id, views, likes) VALUES (LAST_INSERT_ID(), ?, ?)')->execute([$post->views, $post->likes]);
            }
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? 0) === 1062) {
                throw new SubmissionException('Utas ini sudah pernah dikirim.');
            }
            throw $error;
        }
    }

    private function ensureAfterLink(int $postedAt, array $link): void
    {
        if ($link['link_verified_at'] === null || $postedAt < strtotime($link['link_verified_at'])) {
            throw new SubmissionException('Utas diposting sebelum link terdeteksi di profil.');
        }
    }

    private function ensureOpen(?array $campaign): void
    {
        if (!$campaign || Campaign::state($campaign) !== 'open') throw new SubmissionException('Campaign ini sedang tidak menerima utas.');
    }

    private function ensureInPeriod(int $postedAt, array $campaign): void
    {
        if ($postedAt < strtotime($campaign['starts_at'])) {
            throw new SubmissionException('Utas diposting ' . date('d/m/Y H:i', $postedAt) . ' WIB, sebelum campaign dimulai.');
        }
        if ($postedAt > strtotime($campaign['ends_at'])) throw new SubmissionException('Utas diposting setelah campaign selesai.');
    }
}
