<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\User;

final class ProfileController
{
    public function index(): void
    {
        $this->show();
    }

    public function update(): void
    {
        $name = Validate::cleanName(post('name'));
        if ($error = Validate::name($name)) {
            $this->show(['name' => $error], ['name' => $name]);
            return;
        }
        User::updateName((int) Auth::user()['id'], $name);
        Session::flash('Profil disimpan.');
        redirect('/profil');
    }

    public function password(): void
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $new = post('new_password');
        if (!RateLimiter::hit("password:$id", 5, 900)) {
            $this->show(['current_password' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);
            return;
        }
        $errors = array_filter([
            'current_password' => password_verify(post('current_password'), $user['password_hash']) ? null : 'Password saat ini salah.',
            'new_password' => Validate::password($new),
        ]);
        if ($errors) {
            $this->show($errors);
            return;
        }
        User::updatePassword($id, $new);
        RateLimiter::clear("password:$id");
        Auth::login(User::find($id));
        Session::flash('Password diganti. Perangkat lain sudah dikeluarkan.');
        redirect('/profil');
    }

    private function show(array $errors = [], array $old = []): void
    {
        View::render('profile', ['title' => 'Profil', 'active' => 'profile', 'errors' => $errors, 'old' => $old], 'app', $errors ? 422 : 200);
    }
}
