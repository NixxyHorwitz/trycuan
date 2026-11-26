<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClientIp;
use App\Core\PasswordPolicy;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\Setting;
use App\Models\User;
use App\Services\Login;
use App\Services\Seo;

final class AuthController
{
    public function registerForm(): void
    {
        View::render('auth/register', ['title' => setting('seo_daftar_title'), 'seo' => Seo::page('daftar', '/daftar')], 'auth');
    }

    public function register(): void
    {
        $name = Validate::cleanName(post('name'));
        $email = strtolower(trim(post('email')));
        $password = post('password');

        $errors = array_filter([
            'name' => Validate::name($name),
            'email' => Validate::email($email),
            'password' => PasswordPolicy::error($password, [$name, $email]),
            'password_again' => Validate::same($password, post('password_again')),
            'terms' => post('terms') === '1' ? null : 'Centang persetujuan untuk melanjutkan.',
        ]);
        if (!Setting::int('register_open')) $errors = ['form' => 'Pendaftaran sedang ditutup.'];
        if (!$errors && !RateLimiter::hit('register:' . ClientIp::current(), 10, 3600)) {
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
        View::render('auth/register', ['title' => 'Daftar', 'errors' => $errors, 'old' => compact('name', 'email')], 'auth', 422);
    }

    public function loginForm(): void
    {
        $notice = $_SESSION['notice'] ?? null;
        unset($_SESSION['notice']);
        View::render('auth/login', ['title' => 'Masuk', 'errors' => $notice ? ['google' => $notice] : [], 'seo' => ['path' => '/masuk', 'description' => 'Masuk untuk melihat campaign, views, dan saldo.']], 'auth');
    }

    public function login(): void
    {
        $email = strtolower(trim(post('email')));
        [$error, $status] = Login::attempt($email, post('password'), 'freelancer', post('remember') === '1');
        if ($error === null) redirect(Auth::intended());
        View::render('auth/login', ['title' => 'Masuk', 'errors' => ['form' => $error], 'old' => ['email' => $email, 'remember' => post('remember')]], 'auth', $status);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/masuk');
    }
}
