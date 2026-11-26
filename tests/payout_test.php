<?php
// Tes ledger, masa tahan, dan penarikan: php tests/payout_test.php [base_url]
// Memakai data sementara; dihapus di akhir.
declare(strict_types=1);

use App\Core\Crypto;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Ledger;
use App\Models\Participation;
use App\Models\PayoutMethod;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AccountFeeCalculator as Fee;
use App\Services\FeeCredit;
use App\Services\ParticipationManager;
use App\Services\PayoutException;
use App\Services\ProofFile;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsSource;
use App\Services\ViewRefresher;
use App\Services\WithdrawalService;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

// Pekerja untuk tes paralel
if (($argv[1] ?? '') === '--worker') {
    try {
        match ($argv[2]) {
            'credit' => FeeCredit::approve((int) $argv[3], (int) $argv[4], (int) $argv[5]),
            'withdraw' => WithdrawalService::request((int) $argv[3], (int) $argv[4], $argv[5]),
            'reject' => WithdrawalService::reject((int) $argv[3], (int) $argv[4], 'paralel'),
        };
        echo 'ok';
    } catch (PayoutException) {
        echo 'ditolak';
    }
    exit;
}

final class FakeSource implements ThreadsSource
{
    public PostData $next;

    public function post(string $code): PostData
    {
        return $this->next;
    }

    public function profile(string $username): ProfileData
    {
        throw new LogicException();
    }
}

$base = local_base($argv[1] ?? 'http://trycuan.test');
$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};
$error = static function (callable $run): string {
    try {
        $run();
    } catch (PayoutException $e) {
        return $e->getMessage();
    }
    return '';
};
$parallel = static function (array $jobs): array {
    $procs = array_map(static fn (array $args) => popen('"' . PHP_BINARY . '" "' . __FILE__ . '" --worker ' . implode(' ', $args), 'r'), $jobs);
    $out = array_map(static function ($p): string {
        $text = trim((string) stream_get_contents($p));
        pclose($p);
        return $text;
    }, $procs);
    return array_count_values($out);
};

$check('Enkripsi bolak-balik', Crypto::decrypt(Crypto::encrypt('1234567890')) === '1234567890' && Crypto::encrypt('a') !== Crypto::encrypt('a'));
$tampered = Crypto::encrypt('1234567890');
$tampered[30] = chr(ord($tampered[30]) ^ 1);
try {
    Crypto::decrypt($tampered);
    $check('Data diubah terdeteksi', false);
} catch (RuntimeException) {
    $check('Data diubah terdeteksi', true);
}

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = [];
foreach (['a', 'b', 'admin'] as $n) $ids[$n] = User::create("Tes $n", "pay-$n-$run@example.test", $password);
$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$ids['admin']]);
$db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids['a'], (string) random_int(10 ** 15, 10 ** 16), 'pay.a']);
$acc = ThreadsAccount::forUser($ids['a']);
$campaign = Campaign::create(['title' => "Pay $run", 'brief' => 'x', 'destination_url' => 'https://example.com/', 'rules_json' => '{}', 'rate_per_1k' => 10000, 'cap_per_account' => 1000000,
    'target_countries' => 'ID', 'budget' => 1000000, 'starts_at' => date('Y-m-d H:i:s', time() - 864000), 'ends_at' => date('Y-m-d H:i:s', time() + 864000), 'status' => 'active']);
(new ParticipationManager())->join($acc, $campaign);
$p = (int) Participation::find((int) $acc['id'], $campaign)['id'];
$db->prepare("INSERT INTO submissions (participation_id, post_id, post_code, posted_at, views, status, last_checked_at) VALUES (?, ?, ?, NOW(), 5000, 'approved', NOW())")
    ->execute([$p, random_int(10 ** 16, 10 ** 17), substr($run, 0, 10)]);
$s = (int) $db->lastInsertId();
$part = static fn (): array => $db->query("SELECT * FROM participations WHERE id = $p")->fetch();
$views = static function (int $v) use ($db, $s, $p): void {
    $db->exec("UPDATE submissions SET views = $v, last_checked_at = NOW() WHERE id = $s");
    Fee::recalculate($p);
};
$age = static fn (int $hours) => $db->exec("UPDATE fee_holds SET reached_at = reached_at - INTERVAL $hours HOUR WHERE participation_id = $p");
$ready = static fn (): int => FeeCredit::eligible($part())['ready'];
$clear = static function () use ($ids): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
    foreach ($ids as $id) foreach (['payout-method', 'withdraw'] as $k) RateLimiter::clear("$k:$id");
};
$clear();
$proofs = [];

try {
    // Masa tahan
    $views(5000);
    $check('Level baru ditahan', $ready() === 0 && FeeCredit::eligible($part())['next'] > time());
    $age(73);
    $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 5 HOUR WHERE id = $s");
    $check('Belum dibaca ulang setelah masa tahan', $ready() === 0);
    $views(5000);
    $check('Lewat masa tahan dan dibaca ulang', $ready() === 50000);
    $views(8000);
    $check('Level baru tidak ikut siap', $ready() === 50000);
    $views(3000);
    $check('Turun: hanya level yang tersisa', $ready() === 0);
    $views(8000);
    $check('Naik lagi mulai masa tahan baru', $ready() === 0);
    $age(73);
    $views(8000);
    $check('Siap semua', $ready() === 80000);

    $check('Nominal berubah dibatalkan', str_contains($error(fn () => FeeCredit::approve($p, 70000, $ids['admin'])), 'berubah') && Ledger::balance($ids['a']) === 0);
    FeeCredit::setTrust($p, 'rejected', $ids['admin'], 'uji');
    $check('Ditolak: tidak bisa dikreditkan dan anggaran dilepas', $error(fn () => FeeCredit::approve($p, 80000, $ids['admin'])) !== '' && (int) $part()['fee_total'] === 0);
    FeeCredit::setTrust($p, 'review', $ids['admin'], 'uji');
    $check('Dipulihkan mulai masa tahan baru', (int) $part()['fee_total'] === 80000 && $ready() === 0);
    $age(73);
    $views(8000);

    $out = $parallel(array_fill(0, 6, ['credit', $p, 80000, $ids['admin']]));
    $check('Kredit paralel hanya sekali', $out === ['ok' => 1, 'ditolak' => 5] || ($out['ok'] ?? 0) === 1 && array_sum($out) === 6 && ($out['ditolak'] ?? 0) === 5);
    $check('Saldo dan kredit sesuai', Ledger::balance($ids['a']) === 80000 && (int) $part()['fee_credited'] === 80000 && $ready() === 0);
    $views(2000);
    $check('Views turun tidak mengurangi saldo', Ledger::balance($ids['a']) === 80000);
    $views(8000);
    $age(73);
    $views(8000);
    $check('Naik lagi tidak dibayar ganda', $ready() === 0);
    $views(15000);
    $age(73);
    $views(15000);
    $check('Hanya selisih yang siap', $ready() === 70000);
    FeeCredit::approve($p, 70000, $ids['admin']);
    $check('Saldo bertambah selisih', Ledger::balance($ids['a']) === 150000);

    // Views beku
    $src = new FakeSource();
    $refresh = static function (int $v) use ($src, $db, $s, $acc): int {
        $src->next = new PostData('x', $acc['threads_pk'], 'pay.a', time(), '', $v, 1, false);
        $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 1 HOUR, last_attempt_at = NULL WHERE id = $s");
        (new ViewRefresher($src))->refresh(Submission::find($s), $acc);
        return (int) Submission::find($s)['views'];
    };
    $db->exec("UPDATE campaigns SET ends_at = NOW() - INTERVAL 25 HOUR WHERE id = $campaign");
    $check('Setelah beku views tidak naik', $refresh(99000) === 15000 && ViewRefresher::allowed(Campaign::find($campaign)));
    $check('Setelah beku views bisa turun', $refresh(14500) === 14500);
    $db->exec("UPDATE campaigns SET ends_at = NOW() - INTERVAL 31 DAY WHERE id = $campaign");
    $check('Lewat 30 hari tidak bisa dicek', !ViewRefresher::allowed(Campaign::find($campaign)) && !WithdrawalService::needsRefresh($ids['a']));
    $db->exec("UPDATE campaigns SET ends_at = NOW() + INTERVAL 9 DAY WHERE id = $campaign");

    // Penarikan
    $tok = static fn (): string => bin2hex(random_bytes(16));
    $req = static fn (int $amount, ?string $t = null): string => $error(fn () => WithdrawalService::request($ids['a'], $amount, $t ?? $tok()));
    $check('Tanpa rekening ditolak', str_contains($req(50000), 'rekening'));
    PayoutMethod::save($ids['a'], 'BCA', 'Tes A', '1234567890');
    $raw = $db->query("SELECT account_enc FROM payout_methods WHERE user_id = {$ids['a']}")->fetchColumn();
    $check('Nomor tersimpan terenkripsi', !str_contains($raw, '1234567890') && PayoutMethod::masked(PayoutMethod::forUser($ids['a'])) === '•••• 7890');
    $check('Di bawah minimum ditolak', str_contains($req(49999), 'Minimum'));
    $check('Melebihi saldo ditolak', str_contains($req(150001), 'tidak cukup'));
    $check('Token rusak ditolak', $req(50000, 'x') !== '');
    $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 2 HOUR, last_attempt_at = NULL WHERE id = $s");
    $check('Views lama ditolak', str_contains($req(50000), 'Refresh'));
    $db->exec("UPDATE submissions SET last_attempt_at = NOW() WHERE id = $s");

    $out = $parallel(array_map(static fn () => ['withdraw', $ids['a'], 100000, bin2hex(random_bytes(16))], range(1, 6)));
    $check('Penarikan paralel hanya satu', ($out['ok'] ?? 0) === 1 && ($out['ditolak'] ?? 0) === 5 && Ledger::balance($ids['a']) === 50000);
    $w1 = (int) Withdrawal::pendingFor($ids['a'])['id'];
    $out = $parallel(array_fill(0, 6, ['reject', $w1, $ids['admin']]));
    $check('Penolakan paralel mengembalikan sekali', ($out['ok'] ?? 0) === 1 && ($out['ditolak'] ?? 0) === 5 && Ledger::balance($ids['a']) === 150000);
    $same = $tok();
    $out = $parallel(array_fill(0, 6, ['withdraw', $ids['a'], 60000, $same]));
    $check('Token sama paralel tetap satu penarikan', ($out['ok'] ?? 0) === 6 && Ledger::balance($ids['a']) === 90000);
    $w2 = (int) Withdrawal::pendingFor($ids['a'])['id'];
    PayoutMethod::save($ids['a'], 'DANA', 'Tes Baru', '081234567890');
    $check('Ganti rekening tidak mengubah penarikan berjalan', Crypto::decrypt(Withdrawal::find($w2)['account_enc']) === '1234567890');
    try {
        Ledger::add($ids['a'], 'fee', 1, "wd:$w2");
        $check('Kunci ledger unik', false);
    } catch (PDOException) {
        $check('Kunci ledger unik', true);
    }

    // Web
    $wa = new Client($base);
    $check('Saldo butuh masuk', $wa->get('/saldo')->status === 303);
    $wa->post('/masuk', ['email' => "pay-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Halaman saldo', $wa->get('/saldo')->status === 200 && str_contains($wa->body, 'Rp90.000') && !str_contains($wa->body, '081234567890'));
    $check('Rekening butuh password', $wa->post('/saldo/rekening', ['provider' => 'BCA', 'account_name' => 'Tes', 'account_no' => '111122223333', 'password' => 'salah'], '/saldo')->status === 422 && PayoutMethod::forUser($ids['a'])['provider'] === 'DANA');
    $check('Nomor bukan angka ditolak', $wa->post('/saldo/rekening', ['provider' => 'BCA', 'account_name' => 'Tes', 'account_no' => '12ab5678', 'password' => $password], '/saldo')->status === 422);
    $check('Rekening bisa diganti', $wa->post('/saldo/rekening', ['provider' => 'BRI', 'account_name' => 'Tes', 'account_no' => '1111-2222 3333', 'password' => $password], '/saldo')->status === 303 && PayoutMethod::masked(PayoutMethod::forUser($ids['a'])) === '•••• 3333');
    $check('Penarikan kedua lewat web ditolak', $wa->post('/saldo/tarik', ['amount' => '50000', 'token' => $tok()], '/saldo')->status === 422);
    foreach (['-50000', '1e9', '50000.5', ''] as $bad) $check("Nominal ditolak: $bad", $wa->post('/saldo/tarik', ['amount' => $bad, 'token' => $tok()], '/saldo')->status === 422);
    $check('Admin khusus', $wa->get('/admin/fee')->status === 303 && $wa->get('/admin/penarikan')->status === 303 && $wa->post("/admin/penarikan/$w2", ['keputusan' => 'tolak', 'alasan' => 'abc'], '/saldo')->status === 403);

    $wc = new Client($base);
    $wc->post('/admin/masuk', ['email' => "pay-admin-$run@example.test", 'password' => $password], '/admin/masuk');
    PayoutMethod::save($ids['b'], 'BCA', 'Tes B', '1234567890');
    $check('Admin melihat nomor dan peringatan rekening sama', $wc->get('/admin/penarikan')->status === 200 && str_contains($wc->body, '1234567890') && str_contains($wc->body, 'Rekening dipakai user lain'));
    $send = static function (string $content, string $name, string $type) use ($wc, $w2): int {
        $file = tempnam(sys_get_temp_dir(), 'tc');
        file_put_contents($file, $content);
        $status = $wc->post("/admin/penarikan/$w2", ['keputusan' => 'bayar', 'bukti' => new CURLFile($file, $type, $name)], '/admin/penarikan', true)->status;
        unlink($file);
        return $status;
    };
    $check('Bayar tanpa bukti ditolak', $wc->post("/admin/penarikan/$w2", ['keputusan' => 'bayar'], '/admin/penarikan')->status === 422);
    $check('Skrip berkedok gambar ditolak', $send('<?php echo 1;', 'x.png', 'image/png') === 422 && $send("GIF89a<svg onload=1>", 'x.jpg', 'image/jpeg') === 422);
    ob_start();
    imagepng(imagecreatetruecolor(4, 4));
    $png = (string) ob_get_clean();
    $check('Bukti terlalu besar ditolak', $send($png . str_repeat('x', 2 * 1024 * 1024), 'x.png', 'image/png') === 422);
    $check('Bukti sah diterima', $send($png, '../../x.php', 'text/plain') === 303 && Withdrawal::find($w2)['status'] === 'paid');
    $proofs[] = $proof = Withdrawal::find($w2)['proof_file'];
    $check('Bukti bernama acak di luar public', preg_match('/^[0-9a-f]{32}\.png$/', $proof) === 1 && is_file(ProofFile::path($proof)) && $wc->get("/storage/proofs/$proof")->status === 404);
    $check('Pemilik bisa membuka bukti', $wa->get("/penarikan/$w2/bukti")->status === 200 && $wa->body === $png && stripos($wa->headers, 'image/png') !== false);
    $check('Admin membuka bukti dari panel', $wc->get("/admin/penarikan/$w2/bukti")->status === 200 && $wc->body === $png && $wa->get("/admin/penarikan/$w2/bukti")->status === 303);
    $wb = new Client($base);
    $wb->post('/masuk', ['email' => "pay-b-$run@example.test", 'password' => $password], '/masuk');
    $check('User lain tidak bisa membuka bukti', $wb->get("/penarikan/$w2/bukti")->status === 404);
    $check('Yang sudah dibayar tidak bisa ditolak', $wc->post("/admin/penarikan/$w2", ['keputusan' => 'tolak', 'alasan' => 'abc'], '/admin/campaign/baru')->status === 422 && Withdrawal::find($w2)['status'] === 'paid');
    $check('Saldo tetap setelah dibayar', Ledger::balance($ids['a']) === 90000);
    $check('Halaman pencairan fee', $wc->get('/admin/fee')->status === 200);
    $check('Ledger selalu sama dengan jumlah mutasi', (int) $db->query("SELECT SUM(amount) FROM ledger WHERE user_id = {$ids['a']}")->fetchColumn() === 150000 - 60000);
} finally {
    $clear();
    foreach ($proofs as $x) if ($x) ProofFile::delete($x);
    $in = implode(',', array_map('intval', $ids));
    foreach (["ledger WHERE user_id IN ($in)", "withdrawals WHERE user_id IN ($in)", "payout_methods WHERE user_id IN ($in)", "submissions WHERE participation_id = $p", "participations WHERE id = $p",
        "campaigns WHERE id = $campaign", "audit_logs WHERE actor_id IN ($in)", "short_links WHERE account_id = {$acc['id']}", "threads_accounts WHERE user_id IN ($in)"] as $q) $db->exec("DELETE FROM $q");
    $db->exec("DELETE d FROM devices d JOIN user_devices u ON u.device_id = d.id WHERE u.user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
