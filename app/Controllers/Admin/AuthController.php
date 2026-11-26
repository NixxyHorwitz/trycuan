<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\Login;

final class AuthController
{
    public function loginForm(): void
    {
        View::render('admin/login', ['title' => 'Masuk admin'], 'auth');
    }

    public function login(): void
    {
        $email = strtolower(trim(post('email')));
        [$error, $status] = Login::attempt($email, post('password'), 'admin');
        if ($error === null) redirect(Auth::intended());
        View::render('admin/login', ['title' => 'Masuk admin', 'errors' => ['form' => $error], 'old' => ['email' => $email]], 'auth', $status);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/admin/masuk');
    }
}
