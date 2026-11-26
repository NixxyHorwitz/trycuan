<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClientIp;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Models\User;
use App\Services\GoogleLogin;
use App\Services\VerificationException;

final class GoogleController
{
    public function start(): void
    {
        if (!GoogleLogin::enabled()) throw new HttpException(404, 'Login Google sedang tidak aktif.');
        if (!RateLimiter::hit('google:' . ClientIp::current(), 30, 900)) throw new HttpException(429, 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        $user = Auth::user();
        [$url, $flow] = GoogleLogin::begin($user ? (int) $user['id'] : null);
        $_SESSION['google'] = $flow;
        redirect($url);
    }

    public function finish(): void
    {
        if (!GoogleLogin::enabled()) throw new HttpException(404, 'Login Google sedang tidak aktif.');
        $flow = $_SESSION['google'] ?? null;
        unset($_SESSION['google']);
        $user = Auth::user();
        $linking = is_array($flow) && $flow['link'] !== null;
        $back = $user ? '/profil/keamanan' : '/masuk';
        try {
            if (query('error') !== '') throw new VerificationException('Login Google dibatalkan.');
            if (!is_array($flow)) throw new VerificationException('Sesi Google sudah berakhir. Coba lagi.');
            $google = (new GoogleLogin())->identity($flow, query('state'), query('code'));
            if ($linking) {
                // Akun yang menerima sambungan harus sama dengan yang memulai.
                if (!$user || (int) $user['id'] !== $flow['link']) throw new VerificationException('Masuk lagi sebelum menghubungkan Google.');
                GoogleLogin::link((int) $user['id'], $google);
                Session::flash('Akun Google terhubung.');
                redirect('/profil/keamanan');
            }
            $row = GoogleLogin::resolve($google);
            Auth::login($row);
            User::touchLogin((int) $row['id']);
        } catch (VerificationException $error) {
            $_SESSION['notice'] = $error->getMessage();
            redirect($back);
        }
        redirect(Auth::intended());
    }
}
