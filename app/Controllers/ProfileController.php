<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\PasswordPolicy;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\Audit;
use App\Models\Ledger;
use App\Models\PayoutMethod;
use App\Models\ThreadsAccount;
use App\Models\User;

final class ProfileController
{
    /** Akun tanpa password harus baru saja masuk untuk membuatnya. */
    private const FRESH = 900;

    public function index(): void
    {
        $this->show();
    }

    public function update(): void
    {
        $name = Validate::cleanName(post('name'));
        $style = post('avatar_style');
        $seed = post('avatar_seed');
        $user = Auth::user();
        if ($error = Validate::name($name)) {
            $this->show(['name' => $error], ['name' => $name]);
            return;
        }
        User::updateName((int) $user['id'], $name);
        if (isset(User::AVATARS[$style]) && preg_match('/^[0-9a-f]{8,32}$/D', $seed)) User::updateAvatar((int) $user['id'], $style, $seed);
        Session::flash('Profil disimpan.');
        redirect('/profil');
    }

    public function security(): void
    {
        $this->showSecurity();
    }

    public function password(): void
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $new = post('new_password');
        if (!RateLimiter::hit("password:$id", 5, 900)) {
            $this->showSecurity(['current_password' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);
            return;
        }
        $has = (int) $user['has_password'] === 1;
        $errors = array_filter([
            'current_password' => !$has ? null : (password_verify(post('current_password'), $user['password_hash']) ? null : 'Password saat ini salah.'),
            'form' => $has || ($_SESSION['login_at'] ?? 0) > time() - self::FRESH ? null : 'Demi keamanan, keluar lalu masuk lagi dengan Google sebelum membuat password.',
            'new_password' => $has && hash_equals(post('current_password'), $new) ? 'Password baru harus berbeda.' : PasswordPolicy::error($new, [$user['name'], $user['email']]),
            'new_password_again' => Validate::same($new, post('new_password_again')),
        ]);
        if ($errors) {
            $this->showSecurity($errors);
            return;
        }
        User::updatePassword($id, $new);
        RateLimiter::clear("password:$id");
        Auth::login(User::find($id));
        Session::flash('Password diganti. Perangkat lain sudah dikeluarkan.');
        redirect('/profil/keamanan');
    }

    /** Mengeluarkan semua perangkat selain ini. */
    public function sessions(): void
    {
        $id = (int) Auth::user()['id'];
        Database::pdo()->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$id]);
        Auth::login(User::find($id));
        Audit::add($id, 'session.cleared', "user:$id");
        Session::flash('Perangkat lain sudah dikeluarkan.');
        redirect('/profil/keamanan');
    }

    public function unlinkGoogle(): void
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $error = null;
        if (!RateLimiter::hit("password:$id", 5, 900)) $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
        elseif ((int) $user['has_password'] !== 1) $error = 'Buat password dulu supaya kamu tetap bisa masuk.';
        elseif (!password_verify(post('konfirmasi'), $user['password_hash'])) $error = 'Password salah.';
        if ($error) {
            $this->showSecurity(['google' => $error]);
            return;
        }
        RateLimiter::clear("password:$id");
        Database::pdo()->prepare('UPDATE users SET google_sub = NULL WHERE id = ?')->execute([$id]);
        Audit::add($id, 'google.unlinked', "user:$id");
        Session::flash('Akun Google dilepas.');
        redirect('/profil/keamanan');
    }

    private function show(array $errors = [], array $old = []): void
    {
        $id = (int) Auth::user()['id'];
        View::render('profile', ['title' => 'Profil', 'active' => 'profile', 'tab' => 'profil', 'errors' => $errors, 'old' => $old, 'account' => ThreadsAccount::forUser($id), 'balance' => Ledger::balance($id), 'method' => PayoutMethod::forUser($id)], 'app', $errors ? 422 : 200);
    }

    private function showSecurity(array $errors = []): void
    {
        $id = (int) Auth::user()['id'];
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM user_devices WHERE user_id = ?');
        $st->execute([$id]);
        $notice = $_SESSION['notice'] ?? null;
        unset($_SESSION['notice']);
        if ($notice) $errors += ['google' => $notice];
        View::render('security', ['title' => 'Keamanan', 'active' => 'profile', 'tab' => 'keamanan', 'errors' => $errors, 'devices' => (int) $st->fetchColumn()], 'app', $errors && !$notice ? 422 : 200);
    }
}
