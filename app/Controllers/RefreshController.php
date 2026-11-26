<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Services\AutoCredit;
use App\Services\LinkVerifier;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;
use App\Services\VerificationException;
use App\Services\ViewRefresher;

/** Endpoint JSON untuk tombol refresh. */
final class RefreshController
{
    /** Cek link bio, lalu daftar utas yang akan diperbarui satu per satu oleh browser. */
    public function account(): void
    {
        $account = $this->owner();
        $part = Participation::latest((int) $account['id']) ?? throw new HttpException(404, 'Belum ikut campaign.');
        if (!ViewRefresher::allowed(Campaign::find((int) $part['campaign_id']))) {
            $this->json(['message' => 'Masa pembaruan campaign ini sudah berakhir.'], 409);
            return;
        }
        if (!RateLimiter::hit("refresh:{$account['id']}", 1, ViewRefresher::COOLDOWN)) {
            $this->json(['message' => 'Baru saja diperbarui. Coba lagi dalam satu menit.'], 429);
            return;
        }
        try {
            (new LinkVerifier(new ThreadsScraper()))->check($account);
        } catch (VerificationException | ThreadsException) {
            // Status link tampil di card; gagal membaca profil tidak mengubahnya.
        }
        $this->json(['ids' => ViewRefresher::due((int) $account['id'], ViewRefresher::COOLDOWN)]);
    }

    public function post(int $id): void
    {
        $account = $this->owner();
        $submission = Submission::owned($id, (int) $account['id']) ?? throw new HttpException(404, 'Utas tidak ditemukan.');
        if ($submission['status'] === 'rejected' || !ViewRefresher::allowed(Campaign::find((int) $submission['campaign_id']))) {
            $this->json(['result' => 'closed'], 409);
            return;
        }
        if (!RateLimiter::hit("refresh-post:{$account['id']}", 300, 3600)) {
            $this->json(['result' => 'wait'], 429);
            return;
        }
        $result = (new ViewRefresher(new ThreadsScraper()))->refresh($submission, $account);
        if ($result === 'ok') AutoCredit::run((int) $submission['participation_id']);
        $this->json(['result' => $result]);
    }

    private function owner(): array
    {
        $account = ThreadsAccount::forUser((int) Auth::user()['id']) ?? throw new HttpException(403, 'Verifikasi akun Threads dulu.');
        // Lepas kunci sesi supaya halaman lain tetap bisa dibuka selama mengambil data.
        session_write_close();
        return $account;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }
}
