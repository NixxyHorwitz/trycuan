<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Http;
use App\Core\PasswordPolicy;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Services\Mail\Mailer;
use App\Services\PasswordReset;

final class ResetController
{
    public function requestForm(): void
    {
        View::render('auth/forgot', ['title' => 'Lupa password'], 'auth');
    }

    public function request(): void
    {
        $email = strtolower(trim(post('email')));
        if ($error = Validate::email($email)) {
            View::render('auth/forgot', ['title' => 'Lupa password', 'errors' => ['email' => $error], 'old' => ['email' => $email]], 'auth', 422);
            return;
        }
        if (!RateLimiter::hit('reset-ip:' . ClientIp::current(), 10, 3600)) {
            View::render('auth/forgot', ['title' => 'Lupa password', 'errors' => ['email' => 'Terlalu banyak permintaan. Coba lagi dalam satu jam.'], 'old' => ['email' => $email]], 'auth', 429);
            return;
        }
        Session::flash('Jika email terdaftar, link atur ulang dikirim. Periksa juga folder spam.');
        header('Location: /lupa-password', true, 303);
        // Respons dikirim lebih dulu, sehingga waktu kirim email tidak membocorkan email terdaftar.
        Http::finish();
        if (!RateLimiter::hit("reset-mail:$email", 3, 3600)) return;
        try {
            (new PasswordReset(Mailer::make()))->request($email);
        } catch (\Throwable $error) {
            error_log('Email reset gagal: ' . $error->getMessage());
        }
    }

    /** Token dipindah ke sesi supaya tidak tertinggal di riwayat atau referrer. */
    public function form(): void
    {
        header('Referrer-Policy: no-referrer');
        $token = $_GET['token'] ?? null;
        if (is_string($token)) {
            $_SESSION['reset'] = substr($token, 0, 64);
            redirect('/reset-password');
        }
        $this->show();
    }

    public function save(): void
    {
        $token = (string) ($_SESSION['reset'] ?? '');
        $password = post('password');
        if (!RateLimiter::hit('reset-try:' . ClientIp::current(), 20, 3600)) {
            $this->show('Terlalu banyak percobaan. Coba lagi dalam satu jam.', 429);
            return;
        }
        $user = PasswordReset::userFor($token);
        if ($user && ($error = PasswordPolicy::error($password, [$user['name'], $user['email']]) ?? Validate::same($password, post('password_again')))) {
            $this->show($error);
            return;
        }
        if (!PasswordReset::complete($token, $password)) {
            $this->show();
            return;
        }
        unset($_SESSION['reset']);
        Session::flash('Password diganti. Silakan masuk.');
        redirect('/masuk');
    }

    private function show(?string $error = null, int $status = 422): void
    {
        $valid = PasswordReset::userFor((string) ($_SESSION['reset'] ?? '')) !== null;
        View::render('auth/reset', ['title' => 'Atur ulang password', 'valid' => $valid, 'errors' => $error ? ['password' => $error] : []], 'auth', $error ? $status : ($valid ? 200 : 410));
    }
}
