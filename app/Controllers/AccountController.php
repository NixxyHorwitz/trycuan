<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\View;
use App\Models\Click;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\ThreadsAccount;
use App\Services\AccountVerifier;
use App\Services\LinkVerifier;
use App\Services\Threads\Identifier;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;
use App\Services\VerificationException;

final class AccountController
{
    public function index(): void
    {
        $this->show();
    }

    public function start(): void
    {
        $id = $this->userId();
        $input = trim(post('username'));
        $username = Identifier::username($input);
        if ($username === null) {
            $this->show(['username' => 'Isi username Threads, misalnya nama.kamu. Hanya huruf, angka, titik, dan garis bawah.'], $input);
            return;
        }
        if (!RateLimiter::hit("verify-start:$id", 10, 3600)) {
            $this->show(['username' => 'Terlalu banyak membuat kode. Coba lagi dalam satu jam.'], $input, 429);
            return;
        }
        try {
            $this->verifier()->start($id, $username);
        } catch (VerificationException) {
            // Sudah terverifikasi: halaman akun menampilkan statusnya.
        }
        redirect('/akun-threads');
    }

    public function verify(): void
    {
        $id = $this->userId();
        if (ThreadsAccount::forUser($id)) redirect('/akun-threads');
        if (!RateLimiter::hit("verify-gap:$id", 1, 10)) {
            $this->show(['form' => 'Tunggu 10 detik sebelum mencoba lagi.'], '', 429);
            return;
        }
        if (!RateLimiter::hit("verify-check:$id", 20, 3600)) {
            $this->show(['form' => 'Terlalu banyak percobaan. Coba lagi dalam satu jam.'], '', 429);
            return;
        }
        try {
            $this->verifier()->verify($id);
        } catch (VerificationException $error) {
            $this->show(['form' => $error->getMessage()]);
            return;
        } catch (ThreadsException $error) {
            $this->show(['form' => $error->outcome->message()]);
            return;
        }
        Session::flash('Akun Threads terverifikasi. Kode di bio boleh dihapus.');
        redirect('/akun-threads');
    }

    public function cancel(): void
    {
        $this->verifier()->cancel($this->userId());
        redirect('/akun-threads');
    }

    public function checkLink(): void
    {
        $id = $this->userId();
        $account = ThreadsAccount::forUser($id) ?? redirect('/akun-threads');
        if (!RateLimiter::hit("link-gap:$id", 1, 10) || !RateLimiter::hit("link-check:$id", 30, 3600)) {
            $this->show(['link' => 'Terlalu sering memeriksa. Tunggu sebentar lalu coba lagi.'], '', 429);
            return;
        }
        try {
            (new LinkVerifier(new ThreadsScraper()))->check($account);
        } catch (VerificationException $error) {
            $this->show(['link' => $error->getMessage()]);
            return;
        } catch (ThreadsException $error) {
            $this->show(['link' => $error->outcome->message()]);
            return;
        }
        Session::flash('Link terdeteksi di profil.');
        redirect('/akun-threads');
    }

    private function userId(): int
    {
        return (int) Auth::user()['id'];
    }

    private function verifier(): AccountVerifier
    {
        return new AccountVerifier(new ThreadsScraper());
    }

    private function show(array $errors = [], string $username = '', int $errorStatus = 422): void
    {
        $id = $this->userId();
        $account = ThreadsAccount::forUser($id);
        $pending = $account ? null : ThreadsAccount::pending($id);
        $expired = $pending && (int) $pending['expires_at'] <= time();
        $link = $account ? ShortLink::forAccount((int) $account['id']) : null;
        $campaign = $account ? Participation::current((int) $account['id']) : null;
        View::render('account', [
            'link' => $link,
            'campaign' => $campaign,
            'clicks' => $campaign ? Click::validCount((int) $link['id'], (int) $campaign['id']) : 0,
            'week' => $campaign ? Click::week((int) $link['id'], (int) $campaign['id']) : [],
            'title' => 'Akun Threads',
            'active' => 'account',
            'account' => $account,
            'pending' => $expired ? null : $pending,
            'expired' => $expired,
            'errors' => $errors,
            'old' => ['username' => $username ?: ($pending['username'] ?? '')],
        ], 'app', $errors ? $errorStatus : 200);
    }
}
