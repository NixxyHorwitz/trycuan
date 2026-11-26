<?php
// Tes link bagikan dan setoran views: php tests/insight_test.php [base_url]
// Memakai data sementara dan sumber tiruan; dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Models\ViewReport;
use App\Services\AccountFeeCalculator as Fee;
use App\Services\ParticipationManager;
use App\Services\ProofFile;
use App\Services\SubmissionException;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\PostLink;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;
use App\Services\ViewRefresher;
use App\Services\ViewReports;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

final class FakeSource implements ThreadsSource
{
    public function __construct(public string $pk, public ?int $views = null)
    {
    }

    public function post(string $code): PostData
    {
        return new PostData($code, $this->pk, 'x', time(), 't', $this->views, 3, false);
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
    } catch (SubmissionException $e) {
        return $e->getMessage();
    } catch (ThreadsException $e) {
        return $e->outcome->value;
    }
    return '';
};
$db = Database::pdo();

// Link. Transaksi dibatalkan supaya log dan jeda scraper lokal tidak berubah.
$db->beginTransaction();
$db->exec('UPDATE scrape_state SET failures = 0, open_until = 0 WHERE id = 1');
RateLimiter::clear('scrape-global');
$calls = 0;
$reply = [302, 'https://www.threads.com/@siapa/post/Df2VIavI5NH?xmt=abc'];
$links = new PostLink(function (string $path) use (&$calls, &$reply): array {
    $calls++;
    if ($reply instanceof Throwable) throw $reply;
    return $path === '/share/GDpE4fJPL' ? $reply : [404, ''];
});
foreach (['https://www.threads.com/@a/post/Df2VIavI5NH', 'www.threads.com/@a/post/Df2VIavI5NH?x=1', 'Cek ini https://www.threads.net/@a/post/Df2VIavI5NH sekarang'] as $url) {
    $check("Link biasa tanpa request: $url", $links->code($url) === 'Df2VIavI5NH' && $calls === 0);
}
foreach (['https://www.threads.com/share/GDpE4fJPL', 'https://threads.com/share/GDpE4fJPL/?x=1', "Lihat utas ini\nhttps://www.threads.com/share/GDpE4fJPL"] as $url) {
    $check('Link bagikan terurai: ' . str_replace("\n", ' ', $url), $links->code($url) === 'Df2VIavI5NH');
}
$calls = 0;
foreach (['https://evil.test/share/GDpE4fJPL', 'https://www.threads.com.evil.test/share/GDpE4fJPL', 'http://www.threads.com/share/GDpE4fJPL', 'https://www.threads.com@evil.test/share/GDpE4fJPL', 'https://www.threads.com/share/../x', '', 'halo'] as $url) {
    $check("Link asing ditolak tanpa request: $url", $links->code($url) === null && $calls === 0);
}
foreach ([[[302, 'https://evil.test/@a/post/Df2VIavI5NH'], 'not_found'], [[302, 'https://www.threads.com/login'], 'not_found'], [[200, ''], 'not_found'], [[429, ''], 'blocked'], [new ThreadsException(Outcome::Network), 'network']] as [$reply, $want]) {
    $db->exec('UPDATE scrape_state SET failures = 0, open_until = 0 WHERE id = 1');
    $check("Link bagikan gagal: $want", $error(fn () => $links->code('https://www.threads.com/share/GDpE4fJPL')) === $want);
}
$db->rollBack();

$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = $acc = $files = [];
$cid = 0;
foreach (['a', 'b', 'c'] as $n) {
    $ids[$n] = User::create("Tes $n", "ins-$n-$run@example.test", $password);
    $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[$n], (string) random_int(10 ** 15, 10 ** 16), "ins.$n"]);
    $acc[$n] = ThreadsAccount::forUser($ids[$n]);
}
$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$ids['c']]);
$clear = static function () use ($acc): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
    foreach ($acc as $x) RateLimiter::clear("insight:{$x['id']}");
};
$clear();

try {
    $cid = Campaign::create(['title' => "Ins $run", 'brief' => 'x', 'destination_url' => 'https://example.com/', 'rules_json' => '{}', 'rate_per_1k' => 10000, 'cap_per_account' => 500000,
        'target_countries' => 'ID', 'budget' => 1000000, 'starts_at' => date('Y-m-d H:i:s', time() - 86400), 'ends_at' => date('Y-m-d H:i:s', time() + 86400), 'status' => 'active']);
    foreach (['a', 'b'] as $n) (new ParticipationManager())->join($acc[$n], $cid);
    $p = (int) Participation::find((int) $acc['a']['id'], $cid)['id'];
    $seq = random_int(10 ** 17, 10 ** 18);
    $add = static function () use ($db, $p, &$seq): array {
        $id = ++$seq;
        for ($code = '', $x = $id; $x; $x = intdiv($x, 64)) $code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'[$x % 64] . $code;
        $db->prepare("INSERT INTO submissions (participation_id, post_id, post_code, posted_at, status, last_checked_at) VALUES (?, ?, ?, NOW(), 'approved', NOW() - INTERVAL 2 HOUR)")->execute([$p, $id, $code]);
        return Submission::find((int) $db->lastInsertId());
    };
    $post = $add();
    $id = (int) $post['id'];
    $url = "https://www.threads.com/@x/post/{$post['post_code']}";
    $fee = static fn (): int => (int) $db->query("SELECT fee_total FROM participations WHERE id = $p")->fetchColumn();
    $image = static function (): string {
        ob_start();
        $im = imagecreatetruecolor(20, 20);
        for ($i = 0; $i < 60; $i++) imagesetpixel($im, random_int(0, 19), random_int(0, 19), random_int(0, 0xFFFFFF));
        imagepng($im);
        return (string) ob_get_clean();
    };

    // Views opsional
    $src = new FakeSource($acc['a']['threads_pk']);
    $refresh = static function (?int $views) use ($src, $db, $id, $acc): string {
        $src->views = $views;
        $db->exec("UPDATE submissions SET last_checked_at = NOW() - INTERVAL 2 HOUR, last_attempt_at = NULL WHERE id = $id");
        return (new ViewRefresher($src))->refresh(Submission::find($id), $acc['a']);
    };
    $check('Views belum ada bukan kegagalan', $refresh(null) === 'ok' && Submission::find($id)['last_error'] === null && Submission::find($id)['views'] === null && strtotime(Submission::find($id)['last_checked_at']) > time() - 60);

    // Setor lewat web
    $w = new Client($base);
    $path = "/campaign/$cid/setor";
    $check('Butuh masuk', $w->get($path)->status === 303);
    $w->post('/masuk', ['email' => "ins-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Form terbuka dan link terisi', $w->get("$path?utas=$id")->status === 200 && str_contains($w->body, $post['post_code']));
    $check('Tombol setor di campaign', str_contains($w->get("/campaign/$cid")->body, "$path?utas=$id"));
    $send = static function (array $over = [], ?string $content = null, ?Client $as = null) use ($w, $path, $url, $image): Client {
        $file = tempnam(sys_get_temp_dir(), 'tc');
        file_put_contents($file, $content ?? $image());
        $client = ($as ?? $w)->post($path, $over + ['url' => $url, 'views' => '2500', 'bukti' => new CURLFile($file, 'image/png', 'x.png')], $path, true);
        unlink($file);
        return $client;
    };
    $count = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM view_reports WHERE submission_id = $id")->fetchColumn();
    $check('Tanpa screenshot ditolak', $w->post($path, ['url' => $url, 'views' => '2500'], $path)->status === 422);
    $check('Tanpa CSRF ditolak', $w->post($path, ['url' => $url, 'views' => '2500'])->status === 403);
    $check('Views kosong ditolak', $send(['views' => ''])->status === 422 && $send(['views' => '0'])->status === 422 && $send(['views' => '99999999999999999999'])->status === 422);
    $check('PDF dan skrip ditolak', $send([], '%PDF-1.4 x')->status === 422 && $send([], '<?php echo 1;')->status === 422);
    $check('Terlalu besar ditolak', $send([], $image() . str_repeat('x', ViewReports::MAX_FILE))->status === 422);
    $check('Utas belum dikirim ditolak', $send(['url' => 'https://www.threads.com/@x/post/AAAAAAAB'])->status === 422 && str_contains($w->body, 'Kirim utasnya dulu'));
    $wb = new Client($base);
    $wb->post('/masuk', ['email' => "ins-b-$run@example.test", 'password' => $password], '/masuk');
    $check('Utas user lain ditolak', $send([], null, $wb)->status === 422 && $count() === 0);
    $png = $image();
    $check('Setoran diterima', $send([], $png)->status === 303 && $count() === 1);
    $report = $db->query("SELECT * FROM view_reports WHERE submission_id = $id")->fetch();
    $rid = (int) $report['id'];
    $check('Belum menambah views atau fee', Submission::find($id)['views'] === null && $fee() === 0);
    $check('Setoran kedua menunggu yang pertama', $send()->status === 422 && str_contains($w->body, 'masih ditinjau'));
    $other = $add();
    $check('Screenshot sama untuk utas lain ditolak', $send(['url' => "https://www.threads.com/@x/post/{$other['post_code']}"], $png)->status === 422 && str_contains($w->body, 'sudah pernah dikirim'));
    $check('Gagal tidak meninggalkan berkas', count(glob(ROOT . '/storage/proofs/*.png')) === (int) $db->query('SELECT (SELECT COUNT(*) FROM view_reports) + (SELECT COUNT(*) FROM withdrawals WHERE proof_file LIKE "%.png")')->fetchColumn());
    $check('Pemilik melihat screenshot, user lain tidak', $w->get("/insight/$rid/bukti")->status === 200 && str_contains($w->headers, 'image/png') && $wb->get("/insight/$rid/bukti")->status === 404);
    $check('Freelancer tidak bisa meninjau', $w->get('/admin/insight')->status === 303);

    // Admin
    $wc = new Client($base);
    $wc->post('/admin/masuk', ['email' => "ins-c-$run@example.test", 'password' => $password], '/admin/masuk');
    $check('Admin melihat setoran', $wc->get('/admin/insight')->status === 200 && str_contains($wc->body, "/admin/insight/$rid/bukti") && $wc->get("/admin/insight/$rid/bukti")->status === 200);
    $decide = static fn (array $data, int $r): int => $wc->post("/admin/insight/$r", $data, '/admin/insight')->status;
    $check('Tolak tanpa alasan', $decide(['keputusan' => 'tolak'], $rid) === 422);
    $check('Admin mengoreksi dan menyetujui', $decide(['keputusan' => 'setujui', 'views' => '2.400'], $rid) === 303 && (int) Submission::find($id)['views'] === 2400 && Submission::find($id)['view_source'] === 'insight' && $fee() === 20000);
    $check('Tidak bisa disetujui dua kali', $decide(['keputusan' => 'setujui', 'views' => '9000'], $rid) === 422 && $fee() === 20000);
    $check('Views insight bertahan saat Threads belum menampilkan', $refresh(null) === 'ok' && (int) Submission::find($id)['views'] === 2400 && $fee() === 20000);
    $check('Setoran lebih kecil ditolak', $send(['views' => '2400'])->status === 422);
    $check('Setoran berikutnya diterima', $send(['views' => '5200'])->status === 303);
    $second = (int) $db->query("SELECT MAX(id) FROM view_reports WHERE submission_id = $id")->fetchColumn();
    $check('Setoran kedua disetujui', $decide(['keputusan' => 'setujui', 'views' => '5200'], $second) === 303 && $fee() === 50000);
    $check('Pembatalan mengembalikan angka sebelumnya', $decide(['keputusan' => 'tolak', 'alasan' => 'Editan'], $second) === 303 && (int) Submission::find($id)['views'] === 2400 && $fee() === 20000);
    $check('Angka Threads mengambil alih', $refresh(1800) === 'ok' && (int) Submission::find($id)['views'] === 1800 && Submission::find($id)['view_source'] === 'threads' && $fee() === 10000);
    $check('Views Threads yang hilang tetap ditandai', $refresh(null) === 'failed' && (int) Submission::find($id)['views'] === 1800);
    $db->exec("UPDATE submissions SET status = 'rejected' WHERE id = $id");
    $check('Utas ditolak tidak bisa disetor', $error(fn () => ViewReports::submit(['id' => $p, 'campaign_id' => $cid], $post['post_code'], 9000, [])) !== '');
    $db->exec("UPDATE campaigns SET ends_at = NOW() - INTERVAL 2 DAY, starts_at = NOW() - INTERVAL 5 DAY WHERE id = $cid");
    $check('Lewat masa setor ditutup', !ViewReports::open(Campaign::find($cid)) && $w->post($path, ['url' => $url, 'views' => '9999'], '/profil')->status === 422);
} finally {
    $clear();
    foreach ($db->query("SELECT r.proof_file FROM view_reports r JOIN submissions s ON s.id = r.submission_id JOIN participations p ON p.id = s.participation_id WHERE p.campaign_id = $cid")->fetchAll(PDO::FETCH_COLUMN) as $file) ProofFile::delete($file);
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE s FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE p.campaign_id = $cid");
    $db->exec("DELETE h FROM fee_holds h JOIN participations p ON p.id = h.participation_id WHERE p.campaign_id = $cid");
    $db->exec("DELETE FROM participations WHERE campaign_id = $cid");
    $db->exec("DELETE FROM campaigns WHERE id = $cid");
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in)");
    $db->exec("DELETE l FROM short_links l JOIN threads_accounts a ON a.id = l.account_id WHERE a.user_id IN ($in)");
    $db->exec("DELETE FROM threads_accounts WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
