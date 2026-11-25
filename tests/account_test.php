<?php
// Tes verifikasi akun: php tests/account_test.php [base_url]
// Memakai user sementara dan sumber Threads tiruan; dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\AccountVerifier;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;
use App\Services\VerificationException;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

$base = local_base($argv[1] ?? 'http://trycuan.test');
$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};
$error = static function (callable $run): string {
    try {
        $run();
    } catch (VerificationException $e) {
        return $e->getMessage();
    } catch (ThreadsException $e) {
        return $e->outcome->value;
    }
    return '';
};

final class FakeSource implements ThreadsSource
{
    public ProfileData|ThreadsException $next;
    public ?Closure $during = null;
    public int $calls = 0;
    public string $asked = '';

    public function post(string $code): PostData
    {
        throw new LogicException();
    }

    public function profile(string $username): ProfileData
    {
        $this->calls++;
        $this->asked = $username;
        if ($this->during) ($this->during)();
        if ($this->next instanceof ThreadsException) throw $this->next;
        return $this->next;
    }
}

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$pk = (string) random_int(10 ** 15, 10 ** 16);
$password = 'Rahasia-' . $run;
$ids = [];
foreach (['a', 'b', 'c'] as $n) $ids[$n] = User::create("Tes $n", "acc-$n-$run@example.test", $password);
$clear = static function () use ($ids): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
    foreach ($ids as $id) foreach (['start', 'gap', 'check'] as $k) RateLimiter::clear("verify-$k:$id");
};
$clear();

try {
    $src = new FakeSource();
    $v = new AccountVerifier($src);
    $bio = static fn (string $text, bool $private = false, ?string $id = null): ProfileData => new ProfileData($id ?? $pk, 'nama.baru', $text, [], $private);
    [$a, $b, $c] = array_values($ids);

    $check('Tanpa kode ditolak tanpa request', $error(fn () => $v->verify($a)) !== '' && $src->calls === 0);
    $v->start($a, 'tes.akun');
    $code = ThreadsAccount::pending($a)['code'];
    $check('Format kode', preg_match('/^TC-[2-9A-HJKMNP-Z]{8}$/', $code) === 1);
    $v->start($a, 'tes.akun');
    $check('Username sama memakai kode yang sama', ThreadsAccount::pending($a)['code'] === $code);
    $v->start($a, 'lain');
    $check('Ganti username membuat kode baru', ThreadsAccount::pending($a)['code'] !== $code);
    $v->start($a, 'tes.akun');
    $code = ThreadsAccount::pending($a)['code'];

    $src->next = $bio('bio biasa');
    $check('Bio tanpa kode ditolak', str_contains($error(fn () => $v->verify($a)), 'belum ditemukan') && $src->asked === 'tes.akun');
    foreach (["x$code", "{$code}9", strtolower($code)] as $bad) {
        $src->next = $bio($bad);
        $check("Kode tidak utuh ditolak: " . str_replace($code, 'KODE', $bad === strtolower($code) ? 'huruf kecil' : $bad), $error(fn () => $v->verify($a)) !== '');
    }
    $v->start($b, 'tes.akun');
    $other = ThreadsAccount::pending($b)['code'];
    $src->next = $bio("halo $other");
    $check('Kode milik user lain ditolak', str_contains($error(fn () => $v->verify($a)), 'belum ditemukan'));
    $src->next = $bio($code, true);
    $check('Akun privat ditolak', str_contains($error(fn () => $v->verify($a)), 'privat'));
    $src->next = new ThreadsException(Outcome::Network);
    $check('Gagal jaringan tidak memverifikasi', $error(fn () => $v->verify($a)) === 'network' && !ThreadsAccount::forUser($a));

    $db->prepare('UPDATE threads_verifications SET expires_at = ? WHERE user_id = ?')->execute([time() - 1, $a]);
    $calls = $src->calls;
    $src->next = $bio($code);
    $check('Kode kedaluwarsa ditolak tanpa request', str_contains($error(fn () => $v->verify($a)), 'kedaluwarsa') && $src->calls === $calls);
    $v->start($a, 'tes.akun');
    $check('Kode kedaluwarsa diganti', ThreadsAccount::pending($a)['code'] !== $code);
    $code = ThreadsAccount::pending($a)['code'];

    $src->next = $bio($code);
    $src->during = fn () => $v->cancel($a);
    $check('Dibatalkan saat pengecekan tidak lolos', str_contains($error(fn () => $v->verify($a)), 'berubah') && !ThreadsAccount::forUser($a));
    $src->during = null;

    $v->start($a, 'tes.akun');
    $code = ThreadsAccount::pending($a)['code'];
    $src->next = $bio("Penulis\n$code\nJakarta");
    $check('Kode benar terverifikasi', $error(fn () => $v->verify($a)) === '');
    $row = ThreadsAccount::forUser($a);
    $check('ID dan username dari Threads tersimpan', $row['threads_pk'] === $pk && $row['username'] === 'nama.baru');
    $check('Kode hangus', ThreadsAccount::pending($a) === null);
    $check('Tidak bisa membuat kode lagi', $error(fn () => $v->start($a, 'lain')) !== '');

    $src->next = $bio($other);
    $check('Akun milik user lain ditolak', str_contains($error(fn () => $v->verify($b)), 'user lain') && !ThreadsAccount::forUser($b));
    $race = (string) random_int(10 ** 15, 10 ** 16);
    $src->next = $bio($other, false, $race);
    // Meniru user lain menang di antara pengecekan pemilik dan penyimpanan: pemilik dicek lewat unique index.
    $blocked = false;
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$c, $race, 'x']);
        $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$b, $race, 'x']);
    } catch (PDOException $e) {
        $blocked = ($e->errorInfo[1] ?? 0) === 1062;
    }
    $db->rollBack();
    $check('Database menolak satu akun untuk dua user', $blocked);

    // Halaman web
    $w = new Client($base);
    $check('Halaman butuh masuk', $w->get('/akun-threads')->status === 303);
    $w->post('/masuk', ['email' => "acc-c-$run@example.test", 'password' => $password], '/masuk');
    $check('POST tanpa CSRF ditolak', $w->post('/akun-threads', ['username' => 'abc'])->status === 403);
    foreach (['', 'a b', '<b>x</b>', 'https://www.threads.com/@abc', '../etc'] as $bad) {
        $check('Username ditolak: ' . ($bad ?: '(kosong)'), $w->post('/akun-threads', ['username' => $bad], '/akun-threads')->status === 422 && !str_contains($w->body, '<b>x</b>'));
    }
    $check('Username dengan @ diterima', $w->post('/akun-threads', ['username' => '@Tes.Akun_1'], '/akun-threads')->status === 303);
    $pending = ThreadsAccount::pending($c);
    $check('Kode dan username tampil', $pending['username'] === 'tes.akun_1' && str_contains($w->get('/akun-threads')->body, $pending['code']));
    $check('Batal bisa', $w->post('/akun-threads/batal', [], '/akun-threads')->status === 303 && ThreadsAccount::pending($c) === null);
    RateLimiter::hit("verify-gap:$c", 1, 10);
    $check('Jeda verifikasi berlaku', $w->post('/akun-threads/verifikasi', [], '/akun-threads')->status === 429);

    $w2 = new Client($base);
    $w2->post('/masuk', ['email' => "acc-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Akun terverifikasi tampil', str_contains($w2->get('/akun-threads')->body, '@nama.baru') && str_contains($w2->get('/dashboard')->body, '@nama.baru'));
    $w2->post('/akun-threads', ['username' => 'lain', '_csrf' => $w2->get('/dashboard')->csrf()]);
    $check('Akun terverifikasi tidak bisa diganti lewat POST', $w2->status === 303 && ThreadsAccount::pending($a) === null);
} finally {
    $clear();
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in)");
    $db->exec("DELETE l FROM short_links l JOIN threads_accounts a ON a.id = l.account_id WHERE a.user_id IN ($in)");
    $db->exec("DELETE FROM threads_accounts WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
