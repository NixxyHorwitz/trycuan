<?php
// Tes campaign dan submit utas: php tests/campaign_test.php [base_url]
// Memakai data sementara dan sumber Threads tiruan; dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\CampaignForm;
use App\Services\ParticipationManager;
use App\Services\PostRules;
use App\Services\SubmissionException;
use App\Services\SubmissionVerifier;
use App\Services\VerificationException;
use App\Models\ShortLink;
use App\Services\Threads\Identifier;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;

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
    } catch (SubmissionException | VerificationException $e) {
        return $e->getMessage();
    } catch (ThreadsException $e) {
        return $e->outcome->value;
    }
    return '';
};

final class FakeSource implements ThreadsSource
{
    public PostData|ThreadsException $next;
    public ?Closure $during = null;
    public int $calls = 0;

    public function post(string $code): PostData
    {
        $this->calls++;
        if ($this->during) ($this->during)();
        if ($this->next instanceof ThreadsException) throw $this->next;
        return $this->next;
    }

    public array $profiles = [];
    public bool $linked = true;

    public function profile(string $username): ProfileData
    {
        [$pk, $link] = $this->profiles[$username];
        return new ProfileData($pk, $username, '', $this->linked ? ["https://$link"] : [], false);
    }
}

// Aturan teks
$post = static fn (string $text, string $topic = ''): PostData => new PostData('x', '1', 'a', 0, $text, 0, 0, false, $topic);
$rule = static fn (array $r): array => $r + ['required' => [], 'banned' => [], 'min_length' => 0];
foreach ([
    ['Hashtag di teks', 'halo #TryCuan!', '', '#trycuan', true],
    ['Hashtag lebih panjang tidak cocok', '#trycuanku', '', '#trycuan', false],
    ['Topik Threads memenuhi hashtag', 'halo', 'TryCuan', '#trycuan', true],
    ['Topik lain tidak memenuhi', 'halo', 'TryCuan Promo', '#trycuan', false],
    ['Mention di akhir kalimat', 'cek @brand.id.', '', '@brand.id', true],
    ['Mention akun lain tidak cocok', 'cek @brand.id', '', '@brand', false],
    ['Frasa beda spasi', "gratis\n ongkir", '', 'gratis ongkir', true],
    ['Bagian kata tidak cocok', 'kejujuran', '', 'jujur', false],
    ['Karakter regex aman', 'harga 10.000', '', '10x000', false],
] as [$label, $text, $topic, $term, $want]) {
    $check($label, (PostRules::violations($post($text, $topic), $rule(['required' => [$term]])) === []) === $want);
}
$check('Kata terlarang ditolak', PostRules::violations($post('Ini JUDI online'), $rule(['banned' => ['judi online', 'judi']])) !== []);
$check('Topik terlarang ditolak', PostRules::violations($post('halo', 'Judi'), $rule(['banned' => ['judi']])) !== []);
$check('Panjang minimum memakai karakter', PostRules::violations($post('éééé'), $rule(['min_length' => 4])) === [] && PostRules::violations($post('ééé  '), $rule(['min_length' => 4])) !== []);

// Form admin
$form = [
    'title' => 'Campaign Tes', 'brief' => 'Brief <b>tes</b> yang cukup panjang', 'destination_url' => 'https://example.com/a', 'required' => "#trycuan\r\n\r\n#trycuan",
    'banned' => 'judi', 'min_length' => '5', 'rate_per_1k' => '10000', 'cap_per_account' => '100000', 'budget' => '1000000',
    'target_countries' => 'id, MY', 'starts_at' => date('Y-m-d\TH:i', time() - 86400), 'ends_at' => date('Y-m-d\TH:i', time() + 86400), 'status' => 'active',
];
[$valid, $errs] = CampaignForm::parse($form, null, false);
$check('Form benar diterima', $errs === [] && json_decode($valid['rules_json'], true)['required'] === ['#trycuan']);
foreach ([
    ['title', 'a'], ['target_countries', 'Indonesia'], ['target_countries', ''], ['destination_url', 'http://example.com'], ['destination_url', 'javascript:alert(1)'], ['destination_url', 'https://u:p@example.com'],
    ['rate_per_1k', '0'], ['rate_per_1k', '-1'], ['rate_per_1k', '1.5'], ['rate_per_1k', '1e4'], ['cap_per_account', '5000'], ['budget', '99999'],
    ['ends_at', '2026-02-31T10:00'], ['ends_at', $form['starts_at']], ['status', 'ended'], ['banned', '#TRYCUAN'], ['required', '###'], ['min_length', '501'],
] as [$key, $value]) {
    $check("Form ditolak: $key = $value", isset(CampaignForm::parse([$key => $value] + $form, null, false)[1][$key]));
}

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = [];
foreach (['a', 'b', 'c', 'd'] as $n) $ids[$n] = User::create("Tes $n", "camp-$n-$run@example.test", $password);
$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$ids['d']]);
$accounts = [];
foreach (['a', 'b'] as $n) {
    $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[$n], (string) random_int(10 ** 15, 10 ** 16), "tes.$n"]);
    $accounts[$n] = ThreadsAccount::forUser($ids[$n]);
}
$campaigns = [];
$make = static function (array $over = []) use (&$campaigns, $valid, $run): int {
    return $campaigns[] = Campaign::create($over + ['title' => "Tes $run"] + $valid);
};
$clear = static function () use ($accounts): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
    foreach ($accounts as $x) {
        RateLimiter::clear("submit-gap:{$x['id']}");
        RateLimiter::clear("submit:{$x['id']}");
    }
};
$clear();

try {
    [$a, $b] = [$accounts['a'], $accounts['b']];
    $main = $make();
    $other = $make(['title' => "Lain $run"]);
    $upcoming = $make(['starts_at' => date('Y-m-d H:i:s', time() + 3600)]);
    $past = $make(['starts_at' => date('Y-m-d H:i:s', time() - 9000), 'ends_at' => date('Y-m-d H:i:s', time() - 60)]);
    $draft = $make(['status' => 'draft']);
    $paused = $make(['status' => 'paused']);
    $m = new ParticipationManager();
    $status = static fn (int $id, string $s) => $db->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute([$s, $id]);

    foreach (['belum mulai' => $upcoming, 'lewat' => $past, 'draf' => $draft, 'dijeda' => $paused, 'tidak ada' => 999999999] as $label => $id) {
        $check("Ikut campaign $label ditolak", $error(fn () => $m->join($a, $id)) !== '' && !Participation::find((int) $a['id'], $id));
    }
    $src = new FakeSource();
    $v = new SubmissionVerifier($src);
    $start = random_int(10 ** 17, 10 ** 18);
    $n = 0;
    $code = static function () use (&$n, $start): string {
        $id = $start + ++$n;
        $out = '';
        while ($id) {
            $out = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'[$id % 64] . $out;
            $id = intdiv($id, 64);
        }
        return $out;
    };
    $url = static fn (string $c): string => "https://www.threads.com/@siapa.saja/post/$c?xmt=1";
    $data = static function (string $c, array $o = []) use ($a): PostData {
        return new PostData(...array_merge(['code' => $c, 'ownerPk' => $a['threads_pk'], 'ownerUsername' => 'tes.a', 'postedAt' => time() - 600,
            'text' => 'Halo #TryCuan', 'views' => 12, 'likes' => 1, 'isReply' => false, 'topic' => ''], $o));
    };
    $try = static function (array $o = [], ?string $c = null, ?array $acc = null) use ($src, $v, $url, $data, $code, $error, $main, $a): string {
        $c ??= $code();
        $src->next = $data($c, $o);
        return $error(fn () => $v->submit($acc ?? $a, $main, $url($c)));
    };

    $setLink = static fn (array $acc, ?string $at) => $db->prepare('UPDATE short_links SET link_verified_at = ? WHERE account_id = ?')->execute([$at, $acc['id']]);
    foreach ([$a, $b] as $acc) {
        $src->profiles[$acc['username']] = [$acc['threads_pk'], ShortLink::url(ShortLink::forAccount((int) $acc['id']))];
        $setLink($acc, date('Y-m-d H:i:s', time() - 200000));
    }
    $check('Submit sebelum ikut ditolak tanpa request', str_contains($try(), 'Ikuti') && $src->calls === 0);
    $check('Ikut berhasil', $error(fn () => $m->join($a, $main)) === '');
    $check('Rate dan cap terkunci', (int) Participation::find((int) $a['id'], $main)['rate_snapshot'] === 10000 && (int) Participation::find((int) $a['id'], $main)['cap_snapshot'] === 100000);
    $check('Ikut dua kali tetap satu', $error(fn () => $m->join($a, $main)) === '');
    $check('Campaign kedua ditolak', str_contains($error(fn () => $m->join($a, $other)), 'masih mengikuti'));
    $status($main, 'paused');
    $check('Campaign dijeda tetap mengikat', $error(fn () => $m->join($a, $other)) !== '');
    $check('Submit saat dijeda ditolak', str_contains($try(), 'tidak menerima'));
    $status($main, 'active');
    $check('Keluar sebelum mengirim bisa', $error(fn () => $m->leave($a, $main)) === '' && !Participation::find((int) $a['id'], $main));
    $m->join($a, $main);

    $calls = $src->calls;
    foreach (['', 'https://evil.test/@a/post/ABCDEFGH', 'https://www.threads.com.evil.test/@a/post/ABCDEFGH', 'http://127.0.0.1/', 'https://www.threads.com/@a'] as $bad) {
        $check('URL ditolak tanpa request: ' . ($bad ?: '(kosong)'), $error(fn () => $v->submit($a, $main, $bad)) !== '' && $src->calls === $calls);
    }
    $check('Utas milik orang lain ditolak', str_contains($try(['ownerPk' => '999', 'ownerUsername' => 'orang']), '@orang'));
    $check('Username sama tetapi ID beda ditolak', $try(['ownerPk' => '999', 'ownerUsername' => 'tes.a']) !== '');
    $check('Balasan ditolak', str_contains($try(['isReply' => true]), 'balasan'));
    $check('Sebelum periode ditolak', str_contains($try(['postedAt' => time() - 90000]), 'sebelum'));
    $check('Setelah periode ditolak', str_contains($try(['postedAt' => time() + 90000]), 'setelah'));
    $check('Tanpa hashtag wajib ditolak', str_contains($try(['text' => 'Halo semua']), '#trycuan'));
    $check('Topik sebagai hashtag diterima', $try(['text' => 'Halo semua', 'topic' => 'trycuan']) === '');
    $src->next = new ThreadsException(Outcome::Network);
    $check('Gagal jaringan tidak menyimpan', $error(fn () => $v->submit($a, $main, $url($code()))) === 'network');
    $check('Penolakan tidak menyimpan utas', count(Submission::forParticipation((int) Participation::find((int) $a['id'], $main)['id'])) === 1);

    $setLink($a, null);
    $calls = $src->calls;
    $check('Link belum terpasang ditolak tanpa request', str_contains($try(), 'Pasang') && $src->calls === $calls);
    $setLink($a, date('Y-m-d H:i:s', time() - 300));
    $check('Posting sebelum link terpasang ditolak', str_contains($try(), 'sebelum link'));
    $check('Posting setelah link terpasang lolos', $try(['postedAt' => time() - 60]) === '');
    $setLink($a, date('Y-m-d H:i:s', time() - 200000));
    $src->linked = false;
    $check('Link sudah dilepas ditolak', str_contains($try(), 'belum ditemukan') && ShortLink::forAccount((int) $a['id'])['link_verified_at'] === null);
    $src->linked = true;
    $setLink($a, date('Y-m-d H:i:s', time() - 200000));
    $c1 = $code();
    $check('Utas benar diterima', $try([], $c1) === '');
    $row = Submission::forParticipation((int) Participation::find((int) $a['id'], $main)['id'])[0];
    $check('Tersimpan sebagai pending', $row['status'] === 'pending' && $row['post_code'] === $c1 && (int) $row['views'] === 12 && (string) $row['post_id'] === Identifier::postId($c1));
    $calls = $src->calls;
    $check('Duplikat ditolak tanpa request', str_contains($try([], $c1), 'sudah pernah') && $src->calls === $calls);
    $check('Kode dengan awalan nol tetap duplikat', str_contains($try([], 'A' . $c1), 'sudah pernah'));
    $m->join($b, $main);
    $check('Duplikat dari user lain ditolak', str_contains($try([], $c1, $b), 'sudah pernah'));
    $check('User lain tidak bisa memakai utas baru akun A', $try([], null, $b) !== '');
    $check('Views tidak terbaca disimpan null', $try(['views' => null]) === '' && Submission::forParticipation((int) Participation::find((int) $a['id'], $main)['id'])[0]['views'] === null);

    $c2 = $code();
    $src->during = function () use ($db, $a, $main, $c2): void {
        $db->prepare("INSERT INTO submissions (participation_id, post_id, post_code, posted_at, last_checked_at) VALUES (?, ?, ?, NOW(), NOW())")
            ->execute([Participation::find((int) $a['id'], $main)['id'], Identifier::postId($c2), $c2]);
    };
    $check('Kiriman bersamaan hanya masuk satu', str_contains($try([], $c2), 'sudah pernah'));
    $src->during = fn () => $status($main, 'ended');
    $check('Campaign ditutup saat pengecekan tidak lolos', str_contains($try(), 'tidak menerima'));
    $status($main, 'active');
    $src->during = fn () => $m->leave($b, $main);
    $check('Keluar saat pengecekan tidak lolos', str_contains($try(['ownerPk' => $b['threads_pk']], null, $b), 'keluar'));
    $src->during = null;
    $check('Keluar setelah mengirim ditolak', $error(fn () => $m->leave($a, $main)) !== '');
    $db->prepare('UPDATE campaigns SET rate_per_1k = 5000 WHERE id = ?')->execute([$main]);
    $check('Rate peserta lama tidak berubah', (int) Participation::find((int) $a['id'], $main)['rate_snapshot'] === 10000);

    // Halaman web
    $w = new Client($base);
    $check('Halaman butuh masuk', $w->get('/campaign')->status === 303 && $w->get("/campaign/$main")->status === 303);
    $w->post('/masuk', ['email' => "camp-c-$run@example.test", 'password' => $password], '/masuk');
    $check('Daftar campaign', $w->get('/campaign')->status === 200 && str_contains($w->body, "Lain $run") && !str_contains($w->body, "/campaign/$draft\"") && !str_contains($w->body, "/campaign/$paused\""));
    $check('Detail di-escape', $w->get("/campaign/$main")->status === 200 && str_contains($w->body, '&lt;b&gt;tes') && str_contains($w->body, '/akun-threads'));
    foreach ([$draft, $paused, $past, 999999999] as $id) $check('Campaign tertutup 404', $w->get("/campaign/$id")->status === 404);
    $check('ID tidak valid 404', $w->get('/campaign/0')->status === 404 && $w->get('/campaign/1x')->status === 404 && $w->get('/campaign/99999999999999999999')->status === 404);
    $check('Tanpa akun Threads diarahkan', $w->post("/campaign/$main/ikut", [], "/profil")->status === 303 && str_contains($w->headers, '/akun-threads'));
    $check('Freelancer ditolak di admin campaign', $w->get('/admin/campaign')->status === 403 && $w->post('/admin/campaign/baru', $form, '/profil')->status === 403);

    $wa = new Client($base);
    $wa->post('/masuk', ['email' => "camp-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Utas tampil', str_contains($wa->get("/campaign/$main")->body, "/post/$c1"));
    $check('POST tanpa CSRF ditolak', $wa->post("/campaign/$main/utas", ['url' => $url($c1)])->status === 403);
    $check('Duplikat lewat web', $wa->post("/campaign/$main/utas", ['url' => $url($c1)], "/campaign/$main")->status === 422 && str_contains($wa->body, 'sudah pernah'));
    $check('Jeda kirim berlaku', $wa->post("/campaign/$main/utas", ['url' => $url($c1)], "/campaign/$main")->status === 429);
    $check('Muat ulang halaman error aman', $wa->get("/campaign/$main/utas")->status === 200);
    $wb = new Client($base);
    $wb->post('/masuk', ['email' => "camp-b-$run@example.test", 'password' => $password], '/masuk');
    $check('Ikut lewat web', $wb->post("/campaign/$other/ikut", [], "/campaign/$other")->status === 303 && Participation::find((int) $b['id'], $other) !== null);
    $check('Utas user lain tidak terlihat', !str_contains($wb->get("/campaign/$main")->body, "/post/$c1"));
    $check('Dashboard memuat campaign', str_contains($wb->get('/dashboard')->body, "Lain $run"));

    $wd = new Client($base);
    $wd->post('/masuk', ['email' => "camp-d-$run@example.test", 'password' => $password], '/masuk');
    $check('Admin membuat campaign', $wd->post('/admin/campaign/baru', ['title' => "Web $run"] + $form, '/admin/campaign/baru')->status === 303);
    $st = $db->prepare('SELECT id FROM campaigns WHERE title = ?');
    $st->execute(["Web $run"]);
    $campaigns[] = $web = (int) $st->fetchColumn();
    $check('Form salah mempertahankan isian', $wd->post("/admin/campaign/$web", ['budget' => 'x'] + $form, "/admin/campaign/$web")->status === 422 && str_contains($wd->body, 'Campaign Tes'));
    $check('Admin mengubah campaign', $wd->post("/admin/campaign/$web", ['title' => "Web $run", 'status' => 'paused'] + $form, "/admin/campaign/$web")->status === 303 && Campaign::find($web)['status'] === 'paused');
    $edit = ['starts_at' => date('Y-m-d\TH:i', strtotime(Campaign::find($main)['starts_at']))] + $form;
    $check('Tidak bisa kembali ke draf', $wd->post("/admin/campaign/$main", ['status' => 'draft'] + $edit, "/admin/campaign/$main")->status === 422);
    $check('Tanggal mulai terkunci', $wd->post("/admin/campaign/$main", ['starts_at' => date('Y-m-d\TH:i', time() - 999999)] + $edit, "/admin/campaign/$main")->status === 422);
    $check('Campaign lewat tidak bisa dibuka lagi', $wd->post("/admin/campaign/$past", $form, "/admin/campaign/$past")->status === 422 && Campaign::state(Campaign::find($past)) === 'ended');
    $check('Halaman admin terbuka', $wd->get('/admin/campaign')->status === 200 && str_contains($wd->body, "Web $run"));
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
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
