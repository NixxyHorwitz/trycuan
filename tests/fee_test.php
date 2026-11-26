<?php
// Tes fee, refresh views, dan review: php tests/fee_test.php [base_url]
// Memakai data sementara dan sumber Threads tiruan; dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\AccountCard;
use App\Services\AccountFeeCalculator as Fee;
use App\Services\ParticipationManager;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;
use App\Services\ViewRefresher;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

if (($argv[1] ?? '') === '--worker') {
    Fee::recalculate((int) $argv[2]);
    exit;
}

final class FakeSource implements ThreadsSource
{
    public PostData|ThreadsException $next;
    public int $calls = 0;

    public function post(string $code): PostData
    {
        $this->calls++;
        if ($this->next instanceof ThreadsException) throw $this->next;
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

foreach ([[0, 0], [999, 0], [1000, 10000], [1999, 10000], [2100, 20000], [5997, 50000], [-5, 0], [10_000_000, 500000], [PHP_INT_MAX, 500000]] as [$v, $want]) {
    $check("Rumus $v views => $want", Fee::total($v, 10000, 500000) === $want);
}
$check('Cap bukan kelipatan rate', Fee::total(9000, 10000, 25000) === 25000 && Fee::total(2000, 10000, 25000) === 20000);
$check('Fee baru hanya selisih', Fee::fresh(50000, 30000) === 20000 && Fee::fresh(20000, 30000) === 0);

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = $acc = $campaigns = [];
foreach (['a', 'b', 'c'] as $n) {
    $ids[$n] = User::create("Tes $n", "fee-$n-$run@example.test", $password);
    $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[$n], (string) random_int(10 ** 15, 10 ** 16), "fee.$n"]);
    $acc[$n] = ThreadsAccount::forUser($ids[$n]);
}
$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$ids['c']]);
$make = static function (array $o = []) use (&$campaigns, $run): int {
    return $campaigns[] = Campaign::create($o + ['title' => "Fee $run", 'brief' => 'x', 'destination_url' => 'https://example.com/', 'rules_json' => '{}', 'rate_per_1k' => 10000, 'cap_per_account' => 100000,
        'target_countries' => 'ID', 'budget' => 1000000, 'starts_at' => date('Y-m-d H:i:s', time() - 86400), 'ends_at' => date('Y-m-d H:i:s', time() + 86400), 'status' => 'active']);
};
$m = new ParticipationManager();
$seq = random_int(10 ** 16, 10 ** 17);
$add = static function (int $part, ?int $views, string $status = 'approved') use ($db, &$seq): int {
    $db->prepare("INSERT INTO submissions (participation_id, post_id, post_code, posted_at, views, status, last_checked_at) VALUES (?, ?, ?, NOW(), ?, ?, NOW() - INTERVAL 2 HOUR)")
        ->execute([$part, ++$seq, substr(base_convert((string) $seq, 10, 36), 0, 11), $views, $status]);
    return (int) $db->lastInsertId();
};
$clearPosts = static fn (int $part) => $db->exec("DELETE FROM submissions WHERE participation_id = $part");
$row = static fn (int $part): array => $db->query("SELECT * FROM participations WHERE id = $part")->fetch();
$clear = static function () use ($acc): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
    foreach ($acc as $x) foreach (['refresh', 'refresh-post'] as $k) RateLimiter::clear("$k:{$x['id']}");
};
$clear();

try {
    $main = $make();
    $m->join($acc['a'], $main);
    $p = (int) Participation::find((int) $acc['a']['id'], $main)['id'];
    foreach ([[[999, 0, 0], 0], [[600, 400, 0], 10000], [[700, 700, 700], 20000], [[1999, 1999, 1999], 50000]] as [$views, $want]) {
        $clearPosts($p);
        foreach ($views as $v) $add($p, $v);
        Fee::recalculate($p);
        $check('Contoh planning ' . implode('+', $views) . " => $want", (int) $row($p)['fee_total'] === $want && (int) $row($p)['total_views'] === array_sum($views));
    }
    $add($p, 50000, 'pending');
    $add($p, 50000, 'rejected');
    $add($p, null);
    Fee::recalculate($p);
    $check('Pending, ditolak, dan null tidak dihitung', (int) $row($p)['fee_total'] === 50000);
    $add($p, 900000);
    Fee::recalculate($p);
    $check('Cap per akun berlaku', (int) $row($p)['fee_total'] === 100000);

    // Refresh
    $clearPosts($p);
    $id = $add($p, 1500);
    $src = new FakeSource();
    $rf = new ViewRefresher($src);
    $post = static fn (?int $v, ?string $pk = null): PostData => new PostData('x', $pk ?? $acc['a']['threads_pk'], 'fee.a', time(), 't', $v, 3, false);
    $go = static function (PostData|ThreadsException $next, bool $reset = true) use ($src, $rf, $db, $id, $acc): string {
        $src->next = $next;
        if ($reset) $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 2 HOUR, last_attempt_at = NULL WHERE id = $id");
        return $rf->refresh(Submission::find($id), $acc['a']);
    };
    $check('Refresh menaikkan views dan fee', $go($post(2500)) === 'ok' && (int) $row($p)['fee_total'] === 20000);
    $calls = $src->calls;
    $check('Cooldown per utas tanpa request', $go($post(9000), false) === 'wait' && $src->calls === $calls);
    $db->exec("UPDATE participations SET fee_credited = 20000 WHERE id = $p");
    $go($post(1200));
    $check('Views turun: kredit tetap, fee baru nol', (int) $row($p)['fee_total'] === 10000 && Fee::fresh((int) $row($p)['fee_total'], (int) $row($p)['fee_credited']) === 0);
    $go($post(2900));
    $check('Views naik lagi tidak dibayar ganda', Fee::fresh((int) $row($p)['fee_total'], (int) $row($p)['fee_credited']) === 0);
    $go($post(3100));
    $check('Hanya selisih baru', Fee::fresh((int) $row($p)['fee_total'], (int) $row($p)['fee_credited']) === 10000);
    $check('Gagal jaringan mempertahankan angka', $go(new ThreadsException(Outcome::Network)) === 'failed' && (int) Submission::find($id)['views'] === 3100 && Submission::find($id)['last_error'] !== null && (int) $row($p)['total_views'] === 3100);
    $check('Views null tidak menjadi nol', $go($post(null)) === 'failed' && (int) Submission::find($id)['views'] === 3100);
    $check('Pemilik berbeda tidak mengubah angka', $go($post(99999, '9')) === 'failed' && (int) Submission::find($id)['views'] === 3100);
    $check('Gagal tetap kena cooldown', $go($post(1), false) === 'wait');
    $check('Utas hilang keluar dari total', $go(new ThreadsException(Outcome::NotFound)) === 'missing' && (int) $row($p)['total_views'] === 0 && (int) $row($p)['fee_credited'] === 20000);
    $check('Utas kembali dihitung lagi', $go($post(3200)) === 'ok' && (int) $row($p)['total_views'] === 3200 && Submission::find($id)['last_error'] === null);
    $check('Riwayat views tersimpan', (int) $db->query("SELECT COUNT(*) FROM view_snapshots WHERE submission_id = $id")->fetchColumn() === 5 && Submission::viewsByDay($p)[6] === 3200 && Submission::viewsByDay($p)[5] === 0);

    $card = AccountCard::build($acc['a']);
    $check('Card akun', $card['counted'] === 1 && $card['refreshable'] && !$card['partial'] && $card['stale'] === []);
    $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 11 MINUTE, last_attempt_at = NOW() - INTERVAL 11 MINUTE WHERE id = $id");
    $check('Data lama masuk antrean otomatis', AccountCard::build($acc['a'])['stale'] === [$id]);
    $db->exec("UPDATE submissions SET last_attempt_at = NOW() - INTERVAL 2 MINUTE WHERE id = $id");
    $check('Baru dicoba tidak diantre lagi otomatis', AccountCard::build($acc['a'])['stale'] === []);
    $check('Titik grafik saat semua nol', AccountCard::points([0, 0, 0]) === '2,30 60,30 118,30');
    $check('Masa pembaruan', ViewRefresher::allowed(['status' => 'ended', 'ends_at' => date('Y-m-d H:i:s', time() - 3600)]) && !ViewRefresher::frozen(['ends_at' => date('Y-m-d H:i:s', time() - 3600)]) && ViewRefresher::frozen(['ends_at' => date('Y-m-d H:i:s', time() - 90000)]) && !ViewRefresher::allowed(['status' => 'ended', 'ends_at' => date('Y-m-d H:i:s', time() - 31 * 86400)]));

    // Anggaran bersama
    $db->exec("UPDATE campaigns SET status = 'ended' WHERE id = $main");
    $small = $make(['budget' => 100000]);
    $parts = [];
    foreach (['a', 'b', 'c'] as $n) {
        $m->join($acc[$n], $small);
        $parts[] = $x = (int) Participation::find((int) $acc[$n]['id'], $small)['id'];
        $add($x, 6000);
    }
    $procs = array_map(static fn (int $x) => popen('"' . PHP_BINARY . '" "' . __FILE__ . "\" --worker $x", 'r'), [...$parts, ...$parts]);
    $noise = '';
    foreach ($procs as $proc) {
        $noise .= stream_get_contents($proc);
        pclose($proc);
    }
    $fees = array_map(static fn (int $x): int => (int) $row($x)['fee_total'], $parts);
    sort($fees);
    $check('Anggaran tidak terlampaui saat paralel', $fees === [0, 40000, 60000] && $noise === '');
    $db->exec("UPDATE submissions SET views = 1000 WHERE participation_id IN (" . implode(',', $parts) . ')');
    foreach ($parts as $x) Fee::recalculate($x);
    $check('Anggaran terlepas saat views turun', array_map(static fn (int $x): int => (int) $row($x)['fee_total'], $parts) === [10000, 10000, 10000]);

    // Web
    $wa = new Client($base);
    $wa->post('/masuk', ['email' => "fee-a-$run@example.test", 'password' => $password], '/masuk');
    $csrf = $wa->get('/dashboard')->csrf();
    $mineId = (int) $db->query("SELECT id FROM submissions WHERE participation_id = {$parts[0]}")->fetchColumn();
    $otherId = (int) $db->query("SELECT id FROM submissions WHERE participation_id = {$parts[1]}")->fetchColumn();
    $check('Card tampil di dashboard', str_contains($wa->body, 'id="account-card"') && str_contains($wa->body, 'Rp10.000') && str_contains($wa->body, '1.000 views'));
    $check('Antrean otomatis di HTML', str_contains($wa->body, "data-stale=\"$mineId\""));
    $check('Refresh tanpa CSRF ditolak', $wa->post("/utas/$mineId/segarkan", [])->status === 403);
    $check('Refresh utas orang lain 404', $wa->post("/utas/$otherId/segarkan", ['_csrf' => $csrf])->status === 404);
    RateLimiter::hit("refresh:{$acc['a']['id']}", 1, 60);
    $check('Cooldown refresh akun', $wa->post('/segarkan', ['_csrf' => $csrf])->status === 429 && str_contains($wa->headers, 'application/json'));
    $check('Refresh butuh masuk', (new Client($base))->post('/segarkan', [], '/masuk')->status === 303);
    $check('Review khusus admin', $wa->get('/admin/utas')->status === 303 && $wa->post("/admin/utas/$mineId", ['keputusan' => 'setujui', '_csrf' => $csrf])->status === 403);

    $wc = new Client($base);
    $wc->post('/admin/masuk', ['email' => "fee-c-$run@example.test", 'password' => $password], '/admin/masuk');
    $check('Tolak tanpa alasan ditolak', $wc->post("/admin/utas/$mineId", ['keputusan' => 'tolak', 'alasan' => ' '], '/admin/utas')->status === 422 && Submission::find($mineId)['status'] === 'approved');
    $check('Keputusan asing ditolak', $wc->post("/admin/utas/$mineId", ['keputusan' => 'x'], '/admin/utas')->status === 400);
    $check('Tolak mengeluarkan dari total', $wc->post("/admin/utas/$mineId", ['keputusan' => 'tolak', 'alasan' => 'Tidak <b>sesuai'], '/admin/utas')->status === 303 && (int) $row($parts[0])['fee_total'] === 0);
    $check('Alasan di-escape', str_contains($wa->get("/campaign/$small")->body, 'Tidak &lt;b&gt;sesuai'));
    $check('Utas ditolak tidak bisa direfresh', $wa->post("/utas/$mineId/segarkan", ['_csrf' => $csrf])->status === 409);
    $check('Setujui menghitung lagi', $wc->post("/admin/utas/$mineId", ['keputusan' => 'setujui'], '/admin/utas')->status === 303 && (int) $row($parts[0])['fee_total'] === 10000 && Submission::find($mineId)['reject_reason'] === null);
    $check('Keputusan tercatat di audit', (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE actor_id = {$ids['c']} AND action LIKE 'submission.%'")->fetchColumn() === 2);
} finally {
    $clear();
    $in = implode(',', array_map('intval', $ids));
    $cin = implode(',', array_map('intval', $campaigns)) ?: '0';
    $db->exec("DELETE s FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE p.campaign_id IN ($cin)");
    $db->exec("DELETE FROM participations WHERE campaign_id IN ($cin)");
    $db->exec("DELETE FROM campaigns WHERE id IN ($cin)");
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in)");
    $db->exec("DELETE l FROM short_links l JOIN threads_accounts a ON a.id = l.account_id WHERE a.user_id IN ($in)");
    $db->exec("DELETE FROM threads_accounts WHERE user_id IN ($in)");
    $db->exec("DELETE d FROM devices d JOIN user_devices u ON u.device_id = d.id WHERE u.user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
