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
    $password = 'Rahasia-' . $run;
    $form = ['name' => '<script>x</script> Tes', 'email' => strtoupper($email), 'password' => $password, 'terms' => '1'];

    $c = new Client($base);
    $check('POST tanpa CSRF ditolak', $c->post('/daftar', $form)->status === 403);
    $check('Password pendek ditolak', $c->post('/daftar', ['password' => 'abc'] + $form, '/daftar')->status === 422);
    $check('Tanpa persetujuan ditolak', $c->post('/daftar', ['terms' => ''] + $form, '/daftar')->status === 422);
    $check('Input array tidak membuat error server', $c->post('/daftar', ['name' => ['x'], 'email' => ['y']] + $form, '/daftar')->status === 422);

    $before = $c->get('/daftar')->cookie();
    $c->post('/daftar', ['role' => 'admin'] + $form, '/daftar');
    $check('Daftar berhasil', $c->status === 303 && str_contains($c->headers, '/dashboard'));
    $check('ID sesi berganti saat masuk', $c->cookie() !== $before);
    $check('Cookie HttpOnly dan SameSite', stripos($c->headers, 'httponly') !== false && stripos($c->headers, 'samesite=lax') !== false);
    $c->get('/dashboard');
    $check('Nama di-escape', $c->status === 200 && str_contains($c->body, '&lt;script&gt;') && !str_contains($c->body, '<script>x'));
    $check('Role tidak bisa diisi lewat form', $c->get('/admin')->status === 403);
    $check('User masuk tidak melihat form masuk', $c->get('/masuk')->status === 303);
    $check('Keluar tidak bisa lewat GET', $c->get('/keluar')->status === 405);

    $d = new Client($base);
    $check('Email duplikat ditolak', $d->post('/daftar', $form, '/daftar')->status === 422 && str_contains($d->body, 'sudah terdaftar'));
    $check('Password salah ditolak', $d->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk')->status === 422);
    $check('Password benar diterima', $d->post('/masuk', ['email' => $email, 'password' => $password], '/masuk')->status === 303);

    $check('Password lama salah ditolak', $c->post('/profil/password', ['current_password' => 'salah', 'new_password' => 'Baru-12345'], '/profil')->status === 422);
    $check('Ganti password berhasil', $c->post('/profil/password', ['current_password' => $password, 'new_password' => 'Baru-12345'], '/profil')->status === 303);
    $check('Sesi ini tetap masuk', $c->get('/dashboard')->status === 200);
    $check('Sesi lain dikeluarkan', $d->get('/dashboard')->status === 303);

    $check('Ganti nama berhasil', $c->post('/profil', ['name' => 'Nama Baru'], '/profil')->status === 303 && str_contains($c->get('/profil')->body, 'Nama Baru'));
    $old = $c->cookie();
    $check('Keluar berhasil', $c->post('/keluar', [], '/dashboard')->status === 303 && $c->get('/dashboard')->status === 303 && $old !== $c->cookie());

    $db->prepare("UPDATE users SET role = 'admin' WHERE email = ?")->execute([$email]);
    $c->post('/masuk', ['email' => $email, 'password' => 'Baru-12345'], '/masuk');
    $check('Admin bisa membuka halaman admin', $c->get('/admin')->status === 200);
    $db->prepare("UPDATE users SET status = 'blocked' WHERE email = ?")->execute([$email]);
    $check('Blokir langsung memutus sesi', $c->get('/dashboard')->status === 303);

    $x = new Client($base);
    for ($i = 0; $i < 5; $i++) $x->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk');
    $check('Percobaan ke-5 belum dibatasi', $x->status === 422);
    $check('Percobaan ke-6 dibatasi', $x->post('/masuk', ['email' => $email, 'password' => 'salah'], '/masuk')->status === 429);

    foreach (['/.env', '/app/bootstrap.php', '/database/migrate.php', '/storage/logs/app.log', '/bin/set-role.php', '/PLANNING.md', '/%2e%2e/.env'] as $path) {
        $check("File internal tertutup: $path", in_array($x->get($path)->status, [400, 403, 404], true));
    }
} finally {
    $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    $clear();
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
