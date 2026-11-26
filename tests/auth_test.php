<?php
// Tes HTTP terhadap server lokal: php tests/auth_test.php [base_url]
// Memakai user sementara di database .env dan menghapusnya di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

require __DIR__ . '/Client.php';

$base = local_base($argv[1] ?? 'http://trycuan.test');
$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};
$run = bin2hex(random_bytes(5));
$email = "test-$run@example.test";
$clear = static function () use ($email): void {
    foreach (['127.0.0.1', '::1'] as $ip) {
        RateLimiter::clear("register:$ip");
        RateLimiter::clear("login-ip:$ip");
        RateLimiter::clear("login:$ip:$email");
    }
};
$db = Database::pdo();
$clear();

try {
    $password = 'Rahasia-' . bin2hex(random_bytes(5));
    $form = ['name' => '<script>x</script> Tes', 'email' => strtoupper($email), 'password' => $password, 'password_again' => $password, 'terms' => '1'];

    $c = new Client($base);
    $check('POST tanpa CSRF ditolak', $c->post('/daftar', $form)->status === 403);
    $check('Password pendek ditolak', $c->post('/daftar', ['password' => 'abc'] + $form, '/daftar')->status === 422);
    foreach (['pendek' => 'Ab3-efg', 'satu jenis' => 'kucingterbang', 'umum' => 'Password2024!', 'pola' => 'Xk-12345-zz', 'ulang' => 'Xkaaaa-91z', 'email' => "Zz9-test-$run"] as $why => $bad) {
        $check("Password lemah ditolak: $why", $c->post('/daftar', ['password' => $bad, 'password_again' => $bad] + $form, '/daftar')->status === 422);
        $clear();
    }
    $check('Ulangi password harus sama', $c->post('/daftar', ['password_again' => 'Lain-74193x'] + $form, '/daftar')->status === 422 && str_contains($c->body, 'belum sama'));
    $check('Tanpa persetujuan ditolak', $c->post('/daftar', ['terms' => ''] + $form, '/daftar')->status === 422);
    $check('Input array tidak membuat error server', $c->post('/daftar', ['name' => ['x'], 'email' => ['y']] + $form, '/daftar')->status === 422);

    $before = $c->get('/daftar')->cookie();
    $c->post('/daftar', ['role' => 'admin'] + $form, '/daftar');
    $check('Daftar berhasil', $c->status === 303 && str_contains($c->headers, '/dashboard'));
    $check('ID sesi berganti saat masuk', $c->cookie() !== $before);
    $check('Cookie HttpOnly dan SameSite', stripos($c->headers, 'httponly') !== false && stripos($c->headers, 'samesite=lax') !== false);
    $c->get('/dashboard');
    $check('Nama di-escape', $c->status === 200 && str_contains($c->body, '&lt;script&gt;') && !str_contains($c->body, '<script>x'));
    $check('Role tidak bisa diisi lewat form', $c->get('/admin')->status === 303);
    $check('User masuk tidak melihat form masuk', $c->get('/masuk')->status === 303);
    $check('Keluar tidak bisa lewat GET', $c->get('/keluar')->status === 405);

    $d = new Client($base);
    $check('Email duplikat ditolak', $d->post('/daftar', $form, '/daftar')->status === 422 && str_contains($d->body, 'sudah terdaftar'));
    $check('Password salah ditolak', $d->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk')->status === 422);
    $check('Password benar diterima', $d->post('/masuk', ['email' => $email, 'password' => $password], '/masuk')->status === 303);

    $check('Password lama salah ditolak', $c->post('/profil/password', ['current_password' => 'salah', 'new_password' => 'Baru-74193', 'new_password_again' => 'Baru-74193'], '/profil')->status === 422);
    $check('Ganti password berhasil', $c->post('/profil/password', ['current_password' => $password, 'new_password' => 'Baru-74193', 'new_password_again' => 'Baru-74193'], '/profil')->status === 303);
    $check('Sesi ini tetap masuk', $c->get('/dashboard')->status === 200);
    $check('Sesi lain dikeluarkan', $d->get('/dashboard')->status === 303);

    $check('Ganti nama berhasil', $c->post('/profil', ['name' => 'Nama Baru'], '/profil')->status === 303 && str_contains($c->get('/profil')->body, 'Nama Baru'));
    $old = $c->cookie();
    $check('Keluar berhasil', $c->post('/keluar', [], '/dashboard')->status === 303 && $c->get('/dashboard')->status === 303 && $old !== $c->cookie());

    $r = new Client($base);
    $r->get('/saldo?x=1');
    $r->post('/masuk', ['email' => $email, 'password' => 'Baru-74193', 'remember' => '1'], '/masuk');
    $check('Kembali ke halaman tujuan', $r->status === 303 && preg_match('~^location: /saldo?$~mi', $r->headers) === 1);
    $check('Tetap masuk memakai cookie 30 hari', preg_match('/trycuan_session=\w+; expires=/i', $r->headers) === 1);
    $r = new Client($base);
    $r->get('//evil.example/saldo');
    $r->post('/masuk', ['email' => $email, 'password' => 'Baru-74193'], '/masuk');
    $check('Tanpa tetap masuk memakai cookie sesi', preg_match('/trycuan_session=\w+; expires=/i', $r->headers) === 0 && preg_match('~^location: /dashboard?$~mi', $r->headers) === 1);
    $db->prepare("UPDATE users SET role = 'admin' WHERE email = ?")->execute([$email]);
    $c->post('/admin/masuk', ['email' => $email, 'password' => 'Baru-74193'], '/admin/masuk');
    $check('Admin bisa membuka halaman admin', $c->get('/admin')->status === 200);
    $db->prepare("UPDATE users SET status = 'blocked' WHERE email = ?")->execute([$email]);
    $check('Blokir langsung memutus sesi', $c->get('/admin')->status === 303);

    $x = new Client($base);
    for ($i = 0; $i < 5; $i++) $x->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk');
    $check('Percobaan ke-5 belum dibatasi', $x->status === 422);
    $check('Percobaan ke-6 dibatasi', $x->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk')->status === 429);

    foreach (['/.env', '/app/bootstrap.php', '/database/migrate.php', '/storage/logs/app.log', '/bin/admin.php', '/PLANNING.md', '/%2e%2e/.env'] as $path) {
        $check("File internal tertutup: $path", in_array($x->get($path)->status, [400, 403, 404], true));
    }
} finally {
    $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $clear();
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
