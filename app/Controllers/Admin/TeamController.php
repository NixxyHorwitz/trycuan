<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\Announcement;
use App\Models\Audit;
use App\Models\Report;
use App\Models\User;
use App\Services\AdminTeam;
use App\Services\VerificationException;

/** Tim admin, akun sendiri, pengumuman, dan pencarian cepat. */
final class TeamController
{
    public function index(?int $id = null): void
    {
        $this->team();
    }

    public function create(): void
    {
        $old = ['name' => Validate::cleanName(post('name')), 'email' => strtolower(trim(post('email')))];
        try {
            $this->confirmPassword();
            AdminTeam::create($old['name'], $old['email'], post('password'), $this->id());
        } catch (VerificationException $error) {
            $this->team(['form' => $error->getMessage()], $old);
            return;
        }
        Session::flash('Admin ditambahkan.');
        redirect('/admin/tim');
    }

    public function action(int $id): void
    {
        try {
            $this->confirmPassword();
            switch (post('tindakan')) {
                case 'nonaktif':
                    AdminTeam::setStatus($id, 'blocked', $this->id());
                    break;
                case 'aktif':
                    AdminTeam::setStatus($id, 'active', $this->id());
                    break;
                case 'password':
                    if ($id === $this->id()) throw new VerificationException('Ganti password sendiri lewat halaman Akun saya.');
                    AdminTeam::setPassword($id, post('password'), $this->id());
                    break;
                default:
                    throw new HttpException(400, 'Tindakan tidak dikenal.');
            }
        } catch (VerificationException $error) {
            $this->team(['form' => $error->getMessage()]);
            return;
        }
        Session::flash('Perubahan admin disimpan.');
        redirect('/admin/tim');
    }

    public function profile(): void
    {
        View::render('admin/profile', ['title' => 'Akun saya', 'active' => 'me'], 'admin');
    }

    public function saveProfile(): void
    {
        $name = Validate::cleanName(post('name'));
        $new = post('password');
        $errors = array_filter(['name' => Validate::name($name), 'password' => $new === '' ? null : AdminTeam::password($new, [$name, Auth::user()['email']]),
            'password_again' => Validate::same($new, post('password_again'))]);
        try {
            if ($new !== '') $this->confirmPassword();
        } catch (VerificationException $error) {
            $errors['konfirmasi'] = $error->getMessage();
        }
        if ($errors) {
            View::render('admin/profile', ['title' => 'Akun saya', 'active' => 'me', 'errors' => $errors, 'old' => ['name' => $name]], 'admin', 422);
            return;
        }
        User::updateName($this->id(), $name);
        if ($new !== '') {
            AdminTeam::setPassword($this->id(), $new, $this->id());
            Auth::login(User::find($this->id()));
        }
        Session::flash('Akun disimpan.');
        redirect('/admin/akun-saya');
    }

    public function announcements(): void
    {
        $this->notices();
    }

    public function announce(): void
    {
        $old = ['title' => Validate::cleanName(post('title')), 'body' => trim(post('body')), 'level' => post('level')];
        $errors = array_filter([
            'title' => mb_strlen($old['title']) >= 3 && mb_strlen($old['title']) <= 120 ? null : 'Judul 3 sampai 120 karakter.',
            'body' => mb_strlen($old['body']) >= 3 && mb_strlen($old['body']) <= 2000 ? null : 'Isi 3 sampai 2.000 karakter.',
            'level' => in_array($old['level'], ['info', 'penting'], true) ? null : 'Pilih jenis.',
        ]);
        if ($errors) {
            $this->notices($errors, $old);
            return;
        }
        $id = Announcement::create($old['title'], $old['body'], $old['level'], $this->id());
        Audit::add($this->id(), 'announcement.published', "announcement:$id", ['title' => $old['title']]);
        Session::flash('Pengumuman diterbitkan.');
        redirect('/admin/pengumuman');
    }

    public function announcement(int $id): void
    {
        $status = ['arsipkan' => 'archived', 'terbitkan' => 'published'][post('tindakan')] ?? throw new HttpException(400, 'Tindakan tidak dikenal.');
        if (Announcement::setStatus($id, $status)) Audit::add($this->id(), "announcement.$status", "announcement:$id");
        Session::flash($status === 'archived' ? 'Pengumuman diarsipkan.' : 'Pengumuman diterbitkan lagi.');
        redirect('/admin/pengumuman');
    }

    public function search(): void
    {
        $query = mb_substr(query('q'), 0, 60);
        header('Content-Type: application/json');
        echo json_encode(mb_strlen($query) < 2 ? [] : Report::search($query), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function id(): int
    {
        return (int) Auth::user()['id'];
    }

    /** Mengubah kredensial admin selalu meminta password admin yang sedang masuk. */
    private function confirmPassword(): void
    {
        if (!RateLimiter::hit("admin-confirm:{$this->id()}", 10, 900)) throw new VerificationException('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        if (!password_verify(post('konfirmasi'), Auth::user()['password_hash'])) throw new VerificationException('Password kamu salah.');
        RateLimiter::clear("admin-confirm:{$this->id()}");
    }

    private function team(array $errors = [], array $old = []): void
    {
        View::render('admin/team', ['title' => 'Tim admin', 'active' => 'team', 'admins' => AdminTeam::all(), 'errors' => $errors, 'old' => $old], 'admin', $errors ? 422 : 200);
    }

    private function notices(array $errors = [], array $old = []): void
    {
        View::render('admin/announcements', ['title' => 'Pengumuman', 'active' => 'announce', 'rows' => Announcement::all(), 'errors' => $errors, 'old' => $old], 'admin', $errors ? 422 : 200);
    }
}
