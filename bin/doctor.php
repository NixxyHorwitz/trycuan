<?php
// Periksa kesiapan server: php bin/doctor.php [--threads=username] [--mail=alamat]
declare(strict_types=1);

use App\Core\ClientIp;
use App\Core\Database;
use App\Core\Env;
use App\Services\Mail\Mailer;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$options = getopt('', ['threads:', 'mail:']);
$bad = $warn = 0;
$say = static function (string $label, bool $ok, string $fix = '', bool $required = true) use (&$bad, &$warn): void {
    echo ($ok ? 'OK    ' : ($required ? 'GAGAL ' : 'INGAT ')) . $label . ($ok || $fix === '' ? '' : ": $fix") . "\n";
    if (!$ok) $required ? $bad++ : $warn++;
};
$url = Env::get('APP_URL');
$public = str_starts_with($url, 'https://');

$say('PHP 8.3 atau lebih baru', PHP_VERSION_ID >= 80300, 'terpasang ' . PHP_VERSION);
foreach (['pdo_mysql', 'curl', 'mbstring', 'openssl', 'fileinfo', 'gd', 'phar', 'zlib'] as $extension) $say("Ekstensi $extension", extension_loaded($extension), 'pasang ekstensi ini');
$say('APP_KEY', strlen(Env::get('APP_KEY')) >= 64, 'isi 64 karakter acak');
$say('APP_URL', filter_var($url, FILTER_VALIDATE_URL) !== false, 'isi URL lengkap situs');
$say('APP_URL memakai HTTPS', $public, 'wajib untuk situs publik', false);
$say('SHORT_HOST sama dengan host APP_URL', parse_url($url, PHP_URL_HOST) === Env::get('SHORT_HOST'), 'link pendek dilayani aplikasi yang sama', $public);
foreach (['logs', 'sessions', 'proofs'] as $dir) $say("storage/$dir bisa ditulis", is_writable(ROOT . "/storage/$dir"), 'beri izin ke user web server');
try {
    $db = Database::pdo();
    $say('Koneksi database', true);
    $done = $db->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    $pending = array_diff(array_map('basename', glob(ROOT . '/database/migrations/*.sql')), $done);
    $say('Migrasi lengkap', !$pending, 'jalankan php database/migrate.php');
    $say('Ada admin', (bool) $db->query("SELECT 1 FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1")->fetchColumn(), 'jalankan php bin/admin.php buat EMAIL', false);
} catch (PDOException $error) {
    $say('Koneksi database', false, $error->getMessage());
}
$say('Database tidak memakai root', Env::get('DB_USER') !== 'root' && Env::get('DB_PASS') !== '', 'buat user khusus dengan password', $public);
$say('Email SMTP', Env::get('MAIL_DRIVER') === 'smtp' && Env::get('SMTP_HOST') !== '' && filter_var(Env::get('MAIL_FROM'), FILTER_VALIDATE_EMAIL) !== false, 'reset password belum bisa mengirim email', $public);
$say('Turnstile', Env::get('TURNSTILE_SECRET') !== '', 'semua klik akan ditahan', $public);
$say('Turnstile bukan kunci uji', !preg_match('/^[123]x0000/', Env::get('TURNSTILE_SECRET')), 'ganti sebelum publik', $public);
$say('Cloudflare', ClientIp::behindCloudflare(), 'negara tidak diperiksa', false);
$say('Daftar IP hosting', is_file(ROOT . '/storage/ip/hosting.txt'), 'isi storage/ip/hosting.txt dengan CIDR', false);
$newest = max([0, ...array_map('filemtime', glob(ROOT . '/storage/backups/db-*.sql.gz') ?: [])]);
$say('Backup kurang dari 48 jam', $newest > time() - 172800, 'jalankan php bin/backup.php', false);

if (isset($options['threads'])) {
    try {
        $profile = (new ThreadsScraper())->profile(strtolower(ltrim((string) $options['threads'], '@')));
        $say("Threads terbaca (@{$profile->username})", true);
    } catch (ThreadsException $error) {
        $say('Threads terbaca', false, $error->outcome->value . ' ' . $error->getMessage());
    }
}
if (isset($options['mail'])) {
    try {
        Mailer::make()->send((string) $options['mail'], 'Tes email TryCuan', "Email dari TryCuan berfungsi.\n");
        $say('Kirim email', true);
    } catch (RuntimeException $error) {
        $say('Kirim email', false, $error->getMessage());
    }
}
echo "\n" . ($bad ? "$bad masalah harus diperbaiki." : 'Tidak ada masalah wajib.') . ($warn ? " $warn peringatan." : '') . "\n";
exit($bad ? 1 : 0);
