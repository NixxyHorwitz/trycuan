<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\User;

final class AuthController
{
    // Hash acak: menyamakan waktu respons saat email tidak terdaftar.
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuO9Rk0R4oX3dP4sT2l6o4lq2JdC1r8bK';

    public function registerForm(): void
    {
        View::render('auth/register', ['title' => 'Daftar']);
    }

    public function register(): void
    {
        $name = Validate::cleanName(post('name'));
        $email = strtolower(trim(post('email')));
        $password = post('password');

        $errors = array_filter([
            'name' => Validate::name($name),
            'email' => Validate::email($email),
            'password' => Validate::password($password),
            'terms' => post('terms') === '1' ? null : 'Centang persetujuan untuk melanjutkan.',
        ]);
        if (!$errors && !RateLimiter::hit('register:' . $_SERVER['REMOTE_ADDR'], 10, 3600)) {
            $errors['form'] = 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi dalam satu jam.';
        }
        if (!$errors) {
            try {
                $id = User::create($name, $email, $password);
                Auth::login(User::find($id));
                User::touchLogin($id);
                Session::flash('Akun berhasil dibuat.');
                redirect('/dashboard');
            } catch (\PDOException $error) {
                if (($error->errorInfo[1] ?? 0) !== 1062) throw $error;
                $errors['email'] = 'Email sudah terdaftar. Silakan masuk.';
            }
        }
        View::render('auth/register', ['title' => 'Daftar', 'errors' => $errors, 'old' => compact('name', 'email')], 'public', 422);
    }

    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Masuk']);
    }

    public function login(): void
    {
        $email = strtolower(trim(post('email')));
        $ip = $_SERVER['REMOTE_ADDR'];
        $pairKey = "login:$ip:$email";
        $error = null;
        $status = 422;

        // Batas per pasangan IP+email, agar orang lain tidak bisa mengunci akun dari jauh.
        $allowed = RateLimiter::hit("login-ip:$ip", 60, 900);
        $allowed = RateLimiter::hit($pairKey, 5, 900) && $allowed;
        if (!$allowed) {
            $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
            $status = 429;
        } else {
            $user = User::findByEmail($email);
            $match = password_verify(post('password'), $user['password_hash'] ?? self::DUMMY_HASH);
            if (!$user || !$match) {
                $error = 'Email atau password salah.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Akun ini diblokir. Hubungi admin.';
            } else {
                RateLimiter::clear($pairKey);
                Auth::login($user);
                User::touchLogin((int) $user['id']);
                redirect('/dashboard');
            }
        }
        View::render('auth/login', ['title' => 'Masuk', 'errors' => ['form' => $error], 'old' => ['email' => $email]], 'public', $status);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/masuk');
    }
}
