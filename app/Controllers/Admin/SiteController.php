<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Models\Campaign;
use App\Models\Setting;
use App\Models\Visit;
use App\Services\Csv;
use App\Services\Mail\Mailer;
use App\Services\Seo;
use App\Services\SettingsForm;

/** Pengaturan situs, SEO, dan trafik pengunjung. */
final class SiteController
{
    public function settings(): void
    {
        $this->form('settings', self::group(false));
    }

    public function saveSettings(): void
    {
        $this->save('settings', self::group(false), '/admin/pengaturan');
    }

    public function seo(): void
    {
        $group = self::group(true);
        if (query('grup') === '') {
            View::render('admin/seo', ['title' => 'SEO', 'active' => 'seo', 'checks' => self::checks()], 'admin');
            return;
        }
        $this->form('seo', $group);
    }

    public function saveSeo(): void
    {
        $this->save('seo', self::group(true), '/admin/seo');
    }

    public function testMail(): void
    {
        $admin = Auth::user();
        if (!RateLimiter::hit("mail-test:{$admin['id']}", 5, 600)) {
            $this->form('settings', 'email', ['form' => 'Terlalu banyak percobaan. Coba lagi dalam 10 menit.']);
            return;
        }
        try {
            Mailer::make()->send($admin['email'], 'Tes email ' . Setting::str('site_name'), "Email dari panel admin berhasil terkirim.\n");
        } catch (\RuntimeException $error) {
            $this->form('settings', 'email', ['form' => 'Gagal mengirim: ' . $error->getMessage()]);
            return;
        }
        Session::flash('Email tes dikirim ke alamat akun kamu.');
        redirect('/admin/pengaturan?grup=email');
    }

    public function traffic(): void
    {
        $days = in_array(query('hari'), ['1', '7', '30', '90'], true) ? (int) query('hari') : 7;
        $guests = query('siapa') === 'tamu';
        View::render('admin/traffic', ['title' => 'Trafik', 'active' => 'traffic', 'days' => $days, 'guests' => $guests, 'r' => Visit::report($days, $guests)], 'admin');
    }

    public function exportTraffic(): void
    {
        $r = Visit::report(90, false);
        Audit::add((int) Auth::user()['id'], 'traffic.exported', 'traffic');
        Csv::send('trafik', ['Tanggal', 'Pengunjung', 'Tayangan'], array_map(null, $r['labels'], $r['visitors'], $r['views']));
    }

    private static function group(bool $seo): string
    {
        $group = query('grup') ?: post('grup');
        $valid = array_filter(array_keys(Setting::groups()), static fn (string $key): bool => str_starts_with($key, 'seo-') === $seo);
        return in_array($group, $valid, true) ? $group : reset($valid);
    }

    private function save(string $active, string $group, string $path): void
    {
        [$values, $errors] = SettingsForm::save($group, (int) Auth::user()['id']);
        if ($errors) {
            $this->form($active, $group, $errors, $values);
            return;
        }
        Session::flash('Pengaturan disimpan.');
        redirect("$path?grup=$group");
    }

    private function form(string $active, string $group, array $errors = [], array $old = []): void
    {
        View::render('admin/settings', ['title' => $active === 'seo' ? 'SEO' : 'Pengaturan', 'active' => $active, 'group' => $group, 'old' => $old + SettingsForm::values($group), 'errors' => $errors, 'campaigns' => Campaign::all()], 'admin', $errors ? 422 : 200);
    }

    /** @return list<array{0: bool, 1: string, 2: string, 3: string}> lulus, judul, penjelasan, tujuan perbaikan */
    private static function checks(): array
    {
        $size = static fn (string $key, int $min, int $max): bool => mb_strlen(Setting::str($key)) >= $min && mb_strlen(Setting::str($key)) <= $max;
        return [
            [Setting::int('seo_index') === 1, 'Mesin pencari diizinkan', 'Halaman publik boleh diindeks.', 'seo-meta'],
            [str_starts_with(Seo::base(), 'https://'), 'Memakai HTTPS', 'APP_URL di .env harus https:// saat situs publik.', ''],
            [$size('seo_home_title', 15, 60), 'Judul beranda 15 sampai 60 karakter', 'Judul yang terlalu panjang dipotong di hasil pencarian.', 'seo-meta'],
            [$size('seo_home_description', 70, 160), 'Deskripsi beranda 70 sampai 160 karakter', 'Muncul sebagai cuplikan di Google.', 'seo-meta'],
            [$size('seo_panduan_description', 70, 160), 'Deskripsi panduan 70 sampai 160 karakter', 'Halaman panduan adalah konten utama untuk pencarian.', 'seo-meta'],
            [Setting::str('og_image') !== '', 'Gambar berbagi sendiri', 'Saat ini memakai gambar bawaan.', 'seo-sosial'],
            [count(Seo::profiles()) >= 2, 'Profil media sosial terisi', 'Minimal dua, untuk data Organization.', 'seo-sosial'],
            [Setting::str('verify_google') !== '', 'Google Search Console', 'Verifikasi, lalu kirim sitemap.xml.', 'seo-verifikasi'],
            [Setting::str('verify_bing') !== '', 'Bing Webmaster', 'Bing juga dipakai beberapa pencarian AI.', 'seo-verifikasi'],
            [Setting::int('public_campaigns') === 1, 'Daftar campaign publik', 'Menambah halaman yang bisa ditemukan.', ''],
            [count(Seo::faq()) >= 3, 'Tanya jawab terisi', 'Minimal tiga pertanyaan di beranda.', ''],
        ];
    }
}
