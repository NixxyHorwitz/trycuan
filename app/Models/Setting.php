<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;

/** Semua pengaturan yang bisa diubah admin. Nilai bawaan dipakai sampai diubah. */
final class Setting
{
    private const FAQ = "Bagaimana cara menghitung fee? | Views semua utas yang disetujui di akunmu dijumlahkan, lalu dibayar per kelipatan 1.000 views sesuai tarif campaign.\nApakah utas baru harus langsung punya views? | Tidak. Kirim dulu link utasnya. Views bisa terbaca otomatis nanti, atau disetor dengan screenshot insight.\nKapan fee masuk ke saldo? | Setelah masa tahan selesai dan kualitas views serta klik lolos pemeriksaan admin.\nKe mana saldo bisa ditarik? | Ke rekening bank atau e-wallet yang kamu daftarkan di halaman Saldo.\nApakah boleh membeli views atau klik? | Tidak. Akun dengan views atau klik tidak wajar akan ditinjau dan feenya bisa dibatalkan.";
    private const GUIDE = "## Siapkan akun\n\nDaftar, lalu hubungkan satu akun Threads milikmu dengan menempel kode di bio. Kode boleh dihapus setelah akun terverifikasi.\n\n## Pasang link\n\nSetiap akun mendapat satu link pribadi. Pasang di profil Threads **sebelum** posting, karena utas yang diposting lebih dulu tidak dihitung.\n\n## Pilih campaign dan posting\n\n1. Buka halaman campaign dan baca brief.\n2. Pelajari materi dan panduan yang disediakan.\n3. Posting utas asli dengan gayamu sendiri.\n4. Salin link utas dan kirim untuk diperiksa.\n\n## Pantau views\n\nViews dibaca dari Threads bila tersedia. Untuk utas baru yang angkanya belum muncul, setor screenshot insight dari halaman campaign.\n\n## Cairkan fee\n\nFee dihitung dari total views akun per kelipatan 1.000. Setelah masa tahan dan pemeriksaan, fee masuk ke saldo dan bisa ditarik ke bank atau e-wallet.\n\n## Yang tidak boleh\n\n- Membeli views, suka, atau klik.\n- Memakai akun orang lain.\n- Menjanjikan hasil yang tidak benar.";

    /** kelompok => [judul, ikon, keterangan, [kunci => [jenis, label, bawaan, pilihan tambahan]]] */
    public static function groups(): array
    {
        static $groups = null;
        return $groups ??= [
            'umum' => ['Umum', 'settings', 'Identitas dan akses situs.', [
                'site_name' => ['text', 'Nama situs', 'TryCuan', ['min' => 2, 'max' => 40]],
                'site_logo' => ['image', 'Logo', '', ['hint' => 'PNG, JPG, atau WebP persegi. Kosong: ikon bawaan.']],
                'accent' => ['select', 'Warna utama', 'oranye', ['options' => ['oranye' => 'Oranye', 'biru' => 'Biru', 'hijau' => 'Hijau', 'ungu' => 'Ungu', 'merah' => 'Merah muda']]],
                'theme_default' => ['select', 'Tema awal', 'light', ['options' => ['light' => 'Terang', 'dark' => 'Gelap'], 'hint' => 'Untuk pengunjung yang belum memilih tema.']],
                'contact_email' => ['email', 'Email kontak', '', []],
                'contact_url' => ['url', 'Link bantuan', '', ['hint' => 'Misalnya https://wa.me/62...']],
                'footer_text' => ['text', 'Teks kaki halaman', '', ['max' => 160]],
                'register_open' => ['bool', 'Pendaftaran dibuka', 1, []],
                'maintenance' => ['bool', 'Mode perawatan', 0, ['hint' => 'Situs freelancer ditutup sementara. Panel admin dan link pendek tetap jalan.']],
                'maintenance_text' => ['text', 'Pesan perawatan', 'Situs sedang dirawat. Coba lagi beberapa saat.', ['max' => 200]],
            ]],
            'beranda' => ['Halaman depan', 'home', 'Teks untuk pengunjung yang belum masuk.', [
                'home_title' => ['text', 'Judul', 'Posting utas, dibayar dari views', ['min' => 3, 'max' => 90]],
                'home_lead' => ['textarea', 'Pengantar', 'Ikuti campaign, posting di akun Threads kamu, lalu pantau fee dari total views semua utas yang disetujui.', ['max' => 300]],
                'home_cta' => ['text', 'Tombol utama', 'Buat akun', ['min' => 2, 'max' => 30]],
                'home_rate' => ['int', 'Tarif contoh per 1.000 views (Rp)', 10000, ['min' => 100, 'max' => 1000000]],
                'public_campaigns' => ['bool', 'Tampilkan daftar campaign ke publik', 1, ['hint' => 'Hanya judul, tarif, dan periode. Brief tetap khusus anggota.']],
                'guide_text' => ['textarea', 'Panduan umum', self::GUIDE, ['max' => 20000, 'rows' => 14, 'hint' => 'Halaman /panduan. Mendukung ## judul, **tebal**, daftar, dan link.']],
                'faq' => ['textarea', 'Tanya jawab', self::FAQ, ['max' => 8000, 'rows' => 10, 'hint' => 'Satu per baris: Pertanyaan | Jawaban']],
            ]],
            'campaign' => ['Campaign', 'campaign', 'Cara akun mengikuti campaign.', [
                'campaign_mode' => ['select', 'Mode campaign', 'one', ['options' => ['one' => 'Banyak campaign, satu per akun', 'multi' => 'Banyak campaign, akun boleh ikut beberapa', 'focus' => 'Satu campaign utama untuk semua'], 'hint' => 'Mengganti mode tidak mengeluarkan akun dari campaign yang sudah diikuti.']],
                'campaign_focus' => ['campaign', 'Campaign utama', 0, ['hint' => 'Dipakai hanya pada mode satu campaign utama.']],
                'insight_open' => ['bool', 'Setor views dengan screenshot', 1, []],
            ]],
            'fee' => ['Fee dan saldo', 'coins', 'Perubahan masa tahan langsung berlaku untuk fee yang sedang ditahan.', [
                'auto_credit' => ['bool', 'Kredit otomatis akun wajar', 0, ['hint' => 'Nonaktif: semua fee menunggu persetujuan admin.']],
                'hold_days' => ['int', 'Masa tahan fee (hari)', 3, ['min' => 1, 'max' => 30]],
                'min_withdrawal' => ['int', 'Minimum penarikan (Rp)', 50000, ['min' => 10000, 'max' => 10000000]],
                'withdraw_open' => ['bool', 'Penarikan dibuka', 1, []],
                'trust_min_views' => ['int', 'Views minimum untuk penilaian', 5000, ['min' => 1000, 'max' => 1000000, 'hint' => 'Di bawah ini akun berstatus Data kurang.']],
                'trust_min_peers' => ['int', 'Akun pembanding minimum', 5, ['min' => 3, 'max' => 100, 'hint' => 'Akun lain di campaign yang sama dengan views cukup.']],
            ]],
            'google' => ['Login Google', 'key', 'Masuk dan daftar dengan akun Google untuk freelancer.', [
                'google_on' => ['bool', 'Aktifkan login Google', 0, []],
                'google_id' => ['text', 'Client ID', '', ['max' => 200, 'hint' => 'Berakhiran .apps.googleusercontent.com']],
                'google_secret' => ['secret', 'Client secret', '', ['max' => 200]],
            ]],
            'email' => ['Email', 'mail', 'Dipakai untuk reset password. Kosong: memakai berkas .env.', [
                'smtp_host' => ['text', 'Host SMTP', '', ['max' => 190]],
                'smtp_port' => ['int', 'Port', 587, ['min' => 1, 'max' => 65535]],
                'smtp_secure' => ['select', 'Enkripsi', 'tls', ['options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)']]],
                'smtp_user' => ['text', 'Nama pengguna', '', ['max' => 190]],
                'smtp_pass' => ['secret', 'Password SMTP', '', ['max' => 200]],
                'mail_from' => ['email', 'Alamat pengirim', '', []],
            ]],
            'keamanan' => ['Anti bot', 'shield', 'Cloudflare Turnstile di link pendek. Kosong: memakai berkas .env.', [
                'turnstile_site' => ['text', 'Site key', '', ['max' => 100]],
                'turnstile_secret' => ['secret', 'Secret key', '', ['max' => 100]],
            ]],
            'seo-meta' => ['Meta dan halaman', 'search', 'Judul dan deskripsi di hasil pencarian.', [
                'seo_index' => ['bool', 'Izinkan mesin pencari', 1, ['hint' => 'Nonaktifkan selama situs masih uji coba.']],
                'seo_suffix' => ['text', 'Akhiran judul', '{situs}', ['max' => 40, 'hint' => '{situs} diganti nama situs.']],
                'seo_description' => ['textarea', 'Deskripsi bawaan', 'Posting utas Threads sesuai campaign dan dapatkan fee dari total views akun.', ['min' => 20, 'max' => 200]],
                'seo_home_title' => ['text', 'Judul beranda', 'Dibayar dari views utas Threads', ['max' => 70]],
                'seo_home_description' => ['textarea', 'Deskripsi beranda', 'Ikuti campaign, posting utas asli di Threads, dan dapatkan fee dari setiap 1.000 views. Daftar gratis dan tarik saldo ke bank atau e-wallet.', ['max' => 200]],
                'seo_program_title' => ['text', 'Judul daftar campaign', 'Campaign Threads yang sedang dibuka', ['max' => 70]],
                'seo_program_description' => ['textarea', 'Deskripsi daftar campaign', 'Lihat campaign Threads yang sedang berjalan beserta fee per 1.000 views, batas per akun, dan periodenya.', ['max' => 200]],
                'seo_panduan_title' => ['text', 'Judul panduan', 'Panduan menghasilkan dari utas Threads', ['max' => 70]],
                'seo_panduan_description' => ['textarea', 'Deskripsi panduan', 'Langkah lengkap dari verifikasi akun Threads, memasang link, mengirim utas, hingga mencairkan fee.', ['max' => 200]],
                'seo_daftar_title' => ['text', 'Judul daftar akun', 'Daftar gratis', ['max' => 70]],
                'seo_daftar_description' => ['textarea', 'Deskripsi daftar akun', 'Buat akun gratis, hubungkan Threads, dan mulai ikut campaign berbayar.', ['max' => 200]],
            ]],
            'seo-sosial' => ['Media sosial', 'send', 'Pratinjau saat link dibagikan dan profil resmi.', [
                'og_image' => ['image', 'Gambar berbagi', '', ['hint' => '1200 x 630 piksel. Dipakai WhatsApp, Facebook, X, Threads, LinkedIn, Telegram, dan Discord.']],
                'x_handle' => ['text', 'Akun X', '', ['max' => 16, 'hint' => 'Misalnya @trycuan']],
                'fb_app_id' => ['text', 'Facebook App ID', '', ['max' => 30]],
                'social_threads' => ['url', 'Threads', '', []],
                'social_instagram' => ['url', 'Instagram', '', []],
                'social_tiktok' => ['url', 'TikTok', '', []],
                'social_facebook' => ['url', 'Facebook', '', []],
                'social_x' => ['url', 'X', '', []],
                'social_youtube' => ['url', 'YouTube', '', []],
                'social_linkedin' => ['url', 'LinkedIn', '', []],
                'social_telegram' => ['url', 'Telegram', '', []],
            ]],
            'seo-verifikasi' => ['Verifikasi', 'check', 'Isi kode content dari tag meta, bukan seluruh tag.', [
                'verify_google' => ['text', 'Google Search Console', '', ['max' => 120]],
                'verify_bing' => ['text', 'Bing Webmaster', '', ['max' => 120]],
                'verify_yandex' => ['text', 'Yandex', '', ['max' => 120]],
                'verify_pinterest' => ['text', 'Pinterest', '', ['max' => 120]],
                'verify_facebook' => ['text', 'Facebook domain', '', ['max' => 120]],
            ]],
        ];
    }

    private static ?array $cache = null;

    public static function field(string $key): array
    {
        foreach (self::groups() as [, , , $fields]) if (isset($fields[$key])) return $fields[$key];
        throw new \InvalidArgumentException("Pengaturan $key tidak ada");
    }

    public static function str(string $key): string
    {
        self::$cache ??= Database::pdo()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR);
        return (string) (self::$cache[$key] ?? self::field($key)[2]);
    }

    public static function int(string $key): int
    {
        return (int) self::str($key);
    }

    /** Rahasia disimpan terenkripsi. */
    public static function secret(string $key): string
    {
        $value = self::str($key);
        return $value === '' ? '' : Crypto::decrypt((string) base64_decode($value, true));
    }

    /** Pengaturan admin lebih dulu, lalu berkas .env. */
    public static function orEnv(string $key, string $env, string $default = ''): string
    {
        $value = self::field($key)[0] === 'secret' ? self::secret($key) : self::str($key);
        return $value !== '' ? $value : Env::get($env, $default);
    }

    public static function set(string $key, int|string $value): void
    {
        if (self::field($key)[0] === 'secret' && $value !== '') $value = base64_encode(Crypto::encrypt((string) $value));
        Database::pdo()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([$key, (string) $value]);
        self::$cache = null;
    }
}
