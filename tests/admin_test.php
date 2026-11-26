<?php
// Tes penilaian akun dan panel admin: php tests/admin_test.php [base_url]
// Memakai data sementara; pengaturan dan status scraper dikembalikan di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\Device;
use App\Models\Ledger;
use App\Models\Participation;
use App\Models\PayoutMethod;
use App\Models\Setting;
use App\Models\ShortLink;
use App\Models\ThreadsAccount;
use App\Models\Submission;
use App\Models\User;
use App\Services\AccountFeeCalculator as Fee;
use App\Services\AccountTrustEvaluator as Trust;
use App\Services\AutoCredit;
use App\Services\HeldClicks;
use App\Services\ParticipationManager;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;
use App\Services\UserAdmin;
use App\Services\VerificationException;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

final class FakeSource implements ThreadsSource
{
    public ProfileData|ThreadsException $next;

    public function post(string $code): PostData
    {
        throw new LogicException();
    }

    public function profile(string $username): ProfileData
    {
        if ($this->next instanceof ThreadsException) throw $this->next;
        return $this->next;
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
    } catch (VerificationException $e) {
        return $e->getMessage();
    } catch (ThreadsException $e) {
        return $e->outcome->value;
    }
    return '';
};

$check('Median', Trust::median([3.0, 1.0, 2.0]) === 2.0 && Trust::median([4.0, 1.0, 2.0, 3.0]) === 2.5);
$peers = array_map(static fn (int $x): float => Trust::log($x, 10000), [90, 100, 110, 95, 105]);
$check('CTR serupa wajar', abs(Trust::score(Trust::log(60, 10000), $peers)) < 3.5);
$check('CTR nol ekstrem', Trust::score(Trust::log(0, 10000), $peers) < -3.5);
$check('CTR sangat tinggi ekstrem', Trust::score(Trust::log(2000, 10000), $peers) > 3.5);
$check('Satu pembanding curang tidak menggeser', Trust::score(Trust::log(0, 10000), [...$peers, Trust::log(0, 10000)]) < -3.5);

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$settings = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$state = $db->query('SELECT * FROM scrape_state WHERE id = 1')->fetch();
$ids = $accounts = $parts = $devices = [];
$campaign = Campaign::create(['title' => "Adm $run", 'brief' => 'x', 'destination_url' => 'https://example.com/', 'rules_json' => '{}', 'rate_per_1k' => 10000, 'cap_per_account' => 1000000,
    'target_countries' => 'ID', 'budget' => 100000000, 'starts_at' => date('Y-m-d H:i:s', time() - 864000), 'ends_at' => date('Y-m-d H:i:s', time() + 864000), 'status' => 'active']);
$seq = random_int(10 ** 16, 10 ** 17);
for ($i = 0; $i < 6; $i++) {
    $ids[$i] = User::create("Adm $run $i", "adm-$i-$run@example.test", $password);
    $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[$i], (string) random_int(10 ** 15, 10 ** 16), "adm$i.$run"]);
    $accounts[$i] = ThreadsAccount::forUser($ids[$i]);
    ShortLink::markSeen((int) ShortLink::forAccount((int) $accounts[$i]['id'])['id']);
}
$ids[6] = User::create("Adm $run 6", "adm-6-$run@example.test", $password);
$ids[7] = User::create("Adm $run baru", "adm-7-$run@example.test", $password);
$db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[7], (string) random_int(10 ** 15, 10 ** 16), "adm7.$run"]);
ShortLink::forAccount((int) ThreadsAccount::forUser($ids[7])['id']);
$db->exec("UPDATE users SET role = 'admin' WHERE id = {$ids[6]}");
$click = static function (int $i, int $total, string $status, ?int $device = null, string $reasons = '[]') use ($db, &$accounts, $campaign): void {
    $link = ShortLink::forAccount((int) $accounts[$i]['id'])['id'];
    $st = $db->prepare('INSERT INTO clicks (short_link_id, campaign_id, device_id, device_type, risk_score, status, reasons_json) VALUES (?, ?, ?, ?, 0, ?, ?)');
    for ($n = 0; $n < $total; $n++) $st->execute([$link, $campaign, $device, 'mobile', $status, $reasons]);
};
$m = new ParticipationManager();
foreach ($accounts as $i => $x) {
    $m->join($x, $campaign);
    $parts[$i] = (int) Participation::find((int) $x['id'], $campaign)['id'];
    $db->prepare("INSERT INTO submissions (participation_id, post_id, post_code, posted_at, views, likes, status, last_checked_at) VALUES (?, ?, ?, NOW(), 10000, 100, 'approved', NOW())")
        ->execute([$parts[$i], ++$seq, substr(base_convert((string) $seq, 10, 36), 0, 11)]);
    Fee::recalculate($parts[$i]);
    $click($i, 95 + $i * 2, 'valid');
}
$p0 = $parts[0];
$part = static fn (): array => Participation::byId($p0);
$trust = static fn (): array => Trust::evaluate($part());
$clear = static function (): void {
    foreach (['127.0.0.1', '::1'] as $ip) RateLimiter::clear("login-ip:$ip");
};
$clear();

try {
    $db->exec('DELETE FROM settings');
    Setting::set('trust_min_peers', 5);
    $check('Akun wajar', $trust()['status'] === 'wajar' && $trust()['reasons'] === []);
    Setting::set('trust_min_peers', 6);
    $check('Pembanding kurang', $trust()['status'] === 'kurang' && $trust()['reasons'] === ['pembanding_kurang']);
    Setting::set('trust_min_peers', 5);
    $db->exec("UPDATE participations SET total_views = 4999 WHERE id = $p0");
    $check('Views kurang', $trust()['reasons'] === ['views_kurang']);
    $db->exec("UPDATE participations SET total_views = 900000 WHERE id = $p0");
    $check('CTR terlalu rendah', in_array('ctr_rendah', $trust()['reasons'], true) && $trust()['status'] === 'review');
    $db->exec("UPDATE participations SET total_views = 10000 WHERE id = $p0");
    $click(0, 2000, 'valid');
    $check('CTR terlalu tinggi', in_array('ctr_tinggi', $trust()['reasons'], true));
    $link0 = (int) ShortLink::forAccount((int) $accounts[0]['id'])['id'];
    $db->exec("DELETE FROM clicks WHERE short_link_id = $link0");
    $click(0, 95, 'valid');
    $click(0, 80, 'held');
    $check('Banyak klik ditahan', $trust()['reasons'] === ['banyak_ditahan']);
    $db->exec("DELETE FROM clicks WHERE short_link_id = $link0 AND status = 'held'");
    $db->exec("UPDATE submissions SET likes = 1 WHERE participation_id = $p0");
    $check('Suka sangat rendah', $trust()['reasons'] === ['suka_rendah']);
    $db->exec("UPDATE submissions SET likes = 100 WHERE participation_id = $p0");
    ShortLink::markLost($link0);
    $check('Link dilepas', $trust()['reasons'] === ['link_dilepas']);
    ShortLink::markSeen($link0);
    PayoutMethod::save($ids[0], 'BCA', 'Tes', '1234567890');
    PayoutMethod::save($ids[1], 'BCA', 'Tes', '1234567890');
    $check('Rekening sama', $trust()['reasons'] === ['rekening_sama']);
    PayoutMethod::save($ids[1], 'BCA', 'Tes', '1234567891');
    $devices[] = $dev = Device::touch(Device::create());
    $share = $db->prepare('INSERT INTO user_devices (user_id, device_id, last_login_at) VALUES (?, ?, NOW())');
    $share->execute([$ids[0], $dev]);
    $share->execute([$ids[6], $dev]);
    $check('Perangkat admin tidak dihitung', $trust()['status'] === 'wajar');
    $share->execute([$ids[2], $dev]);
    $check('Perangkat freelancer lain', $trust()['reasons'] === ['perangkat_sama']);
    $db->exec("DELETE FROM user_devices WHERE device_id = $dev");
    $sub = (int) $db->query("SELECT id FROM submissions WHERE participation_id = $p0")->fetchColumn();
    $db->exec("UPDATE participations SET total_views = 30000 WHERE id = $p0");
    $db->exec("INSERT INTO view_snapshots (submission_id, views, checked_at) VALUES ($sub, 30000, NOW() - INTERVAL 1 DAY)");
    $check('Lonjakan tanpa klik', in_array('lonjakan_tanpa_klik', $trust()['reasons'], true));
    $db->exec("DELETE FROM view_snapshots WHERE submission_id = $sub");
    Fee::recalculate($p0);

    // Kredit otomatis
    $db->exec("UPDATE fee_holds SET reached_at = reached_at - INTERVAL 4 DAY WHERE participation_id = $p0");
    $db->exec("UPDATE submissions SET last_checked_at = NOW() WHERE participation_id = $p0");
    $check('Kredit otomatis bawaan nonaktif', !AutoCredit::run($p0) && Ledger::balance($ids[0]) === 0);
    Setting::set('auto_credit', 1);
    ShortLink::markLost($link0);
    $check('Perlu review tidak dikreditkan', !AutoCredit::run($p0) && Ledger::balance($ids[0]) === 0);
    ShortLink::markSeen($link0);
    $check('Akun wajar dikreditkan sekali', AutoCredit::run($p0) && !AutoCredit::run($p0) && Ledger::balance($ids[0]) === 100000);
    Setting::set('hold_days', 10);
    $db->exec("UPDATE fee_holds SET reached_at = reached_at - INTERVAL 4 DAY WHERE participation_id = {$parts[1]}");
    $db->exec("UPDATE submissions SET last_checked_at = NOW() WHERE participation_id = {$parts[1]}");
    $check('Masa tahan mengikuti pengaturan', !AutoCredit::run($parts[1]));
    Setting::set('hold_days', 3);

    // Klik ditahan
    $devices[] = $d1 = Device::touch(Device::create());
    $devices[] = $d2 = Device::touch(Device::create());
    $click(0, 2, 'held', $d1, '["lonjakan"]');
    $click(0, 1, 'held', $d2, '["lonjakan"]');
    $click(0, 1, 'held');
    $before = Click::validCount($link0, $campaign);
    $check('Alasan filter terhitung', Click::reasons($link0, $campaign)['lonjakan'] === 3);
    $check('Terima: satu per perangkat', HeldClicks::decide($part(), true, $ids[6], 'uji') === 2 && Click::validCount($link0, $campaign) === $before + 2 && $trust()['held'] === 0);
    $click(0, 3, 'held', $d1);
    $check('Perangkat yang sudah valid tidak dihitung lagi', HeldClicks::decide($part(), true, $ids[6], 'uji') === 0);
    $click(0, 3, 'held', $d2);
    HeldClicks::decide($part(), false, $ids[6], 'uji');
    $check('Buang: tidak menambah klik valid', Click::validCount($link0, $campaign) === $before + 2 && $trust()['held'] === 0);

    // Tindakan user
    $src = new FakeSource();
    $src->next = new ProfileData('999', 'orang.lain', '', [], false);
    $check('Username akun lain ditolak', str_contains($error(fn () => UserAdmin::rename($ids[0], 'orang.lain', $ids[6], $src)), 'berbeda'));
    $src->next = new ThreadsException(Outcome::Network);
    $check('Gagal jaringan tidak mengubah username', $error(fn () => UserAdmin::rename($ids[0], 'x', $ids[6], $src)) === 'network');
    $src->next = new ProfileData($accounts[0]['threads_pk'], "ganti.$run", '', [], false);
    $check('Username baru dengan ID sama diterima', $error(fn () => UserAdmin::rename($ids[0], "ganti.$run", $ids[6], $src)) === '' && ThreadsAccount::forUser($ids[0])['username'] === "ganti.$run");
    $check('Akun dengan campaign tidak bisa dilepas', str_contains($error(fn () => UserAdmin::detach($ids[0], $ids[6], 'uji')), 'campaign'));
    $check('Akun tanpa campaign bisa dilepas', $error(fn () => UserAdmin::detach($ids[7], $ids[6], 'uji')) === '' && ThreadsAccount::forUser($ids[7]) === null);

    // Web
    $w = new Client($base);
    $w->post('/masuk', ['email' => "adm-6-$run@example.test", 'password' => $password], '/masuk');
    $check('Admin ditolak di login freelancer', $w->status === 422 && $w->get('/dashboard')->status === 303);
    $w->post('/admin/masuk', ['email' => "adm-3-$run@example.test", 'password' => $password], '/admin/masuk');
    $check('Freelancer ditolak di login admin', $w->status === 422 && $w->get('/admin')->status === 303);
    $w->post('/admin/masuk', ['email' => "adm-6-$run@example.test", 'password' => $password], '/admin/masuk');
    $check('Admin masuk ke panel', $w->status === 303 && str_contains($w->headers, 'trycuan_admin') && stripos($w->headers, 'path=/admin') !== false && $w->get('/dashboard')->status === 303);
    foreach (['/admin' => 'Fee siap', '/admin/user' => "Adm $run", '/admin/fee' => 'Pencairan', '/admin/scraper' => 'Request 24 jam', '/admin/audit' => 'clicks.accepted', '/admin/pengaturan?grup=fee' => 'Masa tahan', '/admin/pengaturan' => 'Nama situs', '/admin/pengaturan?grup=google' => 'Redirect URI', '/admin/seo' => 'Kesiapan', '/admin/seo?grup=seo-sosial' => 'Gambar berbagi', '/admin/trafik' => 'Sedang online', "/admin/campaign/$campaign/dokumen" => 'Berkas unduhan', "/admin/campaign/$campaign/dokumen/baru" => 'Isi dokumen', '/admin/tim' => 'Tambah admin', '/admin/akun-saya' => 'Password baru', '/admin/pengumuman' => 'Buat pengumuman', '/admin/klik' => 'Log klik', '/admin/utas' => 'Menunggu', '/admin/penarikan' => 'Penarikan', "/admin/campaign/$campaign/ringkasan" => 'Peringkat akun', "/admin/campaign/$campaign/duplikat" => '(salinan)', "/admin/akun/$p0" => 'Median', "/admin/user/{$ids[0]}" => 'Tindakan'] as $path => $text) {
        $check("Halaman $path", $w->get($path)->status === 200 && str_contains($w->body, $text));
    }
    $check('Pencarian', str_contains($w->get('/admin/user?cari=' . urlencode("ganti.$run"))->body, "Adm $run 0") && str_contains($w->get('/admin/user?cari=%25%25zzz')->body, 'Tidak ada user'));
    $check('Parameter aneh aman', $w->get('/admin/user?cari[]=x')->status === 200 && $w->get('/admin/audit?sebelum[]=x')->status === 200 && $w->get('/admin/audit?sebelum=-1')->status === 200);
    $f = new Client($base);
    $f->post('/masuk', ['email' => "adm-3-$run@example.test", 'password' => $password], '/masuk');
    foreach (['/admin', '/admin/user', '/admin/scraper', '/admin/audit', '/admin/pengaturan', "/admin/akun/$p0"] as $path) $check("Freelancer ditolak $path", $f->get($path)->status === 303 && str_contains($f->headers, '/admin/masuk'));
    foreach (["/admin/user/{$ids[0]}", '/admin/scraper', '/admin/pengaturan', "/admin/akun/$p0"] as $path) $check("Freelancer ditolak POST $path", $f->post($path, ['tindakan' => 'blokir', 'alasan' => 'abc'], '/profil')->status === 403);
    $check('Blokir tanpa alasan ditolak', $w->post("/admin/user/{$ids[3]}", ['tindakan' => 'blokir', 'alasan' => ''], '/admin/user')->status === 422);
    $check('Blokir memutus sesi', $w->post("/admin/user/{$ids[3]}", ['tindakan' => 'blokir', 'alasan' => 'uji <b>'], '/admin/pengaturan')->status === 303 && $f->get('/dashboard')->status === 303);
    $check('Link user diblokir mati', $w->get('/' . ShortLink::forAccount((int) $accounts[3]['id'])['code'])->status === 404);
    $check('Alasan audit di-escape', str_contains($w->get('/admin/audit')->body, 'uji &lt;b&gt;'));
    $check('Buka blokir', $w->post("/admin/user/{$ids[3]}", ['tindakan' => 'buka', 'alasan' => 'uji'], '/admin/pengaturan')->status === 303 && User::find($ids[3])['status'] === 'active');
    $check('Admin tidak bisa memblokir diri', $w->post("/admin/user/{$ids[6]}", ['tindakan' => 'blokir', 'alasan' => 'uji'], '/admin/pengaturan')->status === 422);
    $ok = ['auto_credit' => '0', 'hold_days' => '5', 'min_withdrawal' => '75000', 'trust_min_views' => '5000', 'trust_min_peers' => '5', 'withdraw_open' => '1'];
    $check('Pengaturan di luar batas ditolak', $w->post('/admin/pengaturan?grup=fee', ['hold_days' => '0'] + $ok, '/admin/pengaturan')->status === 422 && $w->post('/admin/pengaturan?grup=fee', ['auto_credit' => '2'] + $ok, '/admin/pengaturan')->status === 422);
    $check('Pengaturan tersimpan', $w->post('/admin/pengaturan?grup=fee', $ok, '/admin/pengaturan')->status === 303 && $db->query("SELECT setting_value FROM settings WHERE setting_key = 'min_withdrawal'")->fetchColumn() === '75000');
    $db->exec('UPDATE scrape_state SET failures = 7, open_until = ' . (time() + 500) . ' WHERE id = 1');
    $check('Scraper dijeda terlihat', str_contains($w->get('/admin/scraper')->body, 'Dijeda') && str_contains($w->get('/admin')->body, 'Scraper dijeda'));
    $check('Jeda bisa dibuka', $w->post('/admin/scraper', [], '/admin/scraper')->status === 303 && (int) $db->query('SELECT open_until FROM scrape_state WHERE id = 1')->fetchColumn() === 0);
    // Fitur panel
    $check('Admin tidak bisa dibuka sebagai freelancer', $w->get("/admin/user/{$ids[6]}")->status === 404);
    $check('Filter dan urutan aman', $w->get('/admin/user?status[]=x&urut=1;drop&halaman=-9')->status === 200 && $w->get('/admin/audit?jenis=%25')->status === 200 && $w->get('/admin/klik?status=x')->status === 200 && $w->get('/admin?hari=999')->status === 200);
    $hits = json_decode($w->get('/admin/cari?q=' . urlencode("Adm $run"))->body, true);
    $check('Pencarian cepat hanya freelancer', count($hits) >= 1 && !in_array("/admin/user/{$ids[6]}", array_column($hits, 'url'), true) && $w->get('/admin/cari?q=a')->body === '[]');
    $check('Ekspor CSV', $w->get('/admin/user/ekspor?cari=' . urlencode("Adm $run"))->status === 200 && str_contains($w->body, "adm-3-$run") && !str_contains($w->body, "adm-6-$run") && $w->get('/admin/audit/ekspor')->status === 200 && $w->get('/admin/penarikan/ekspor')->status === 200);
    $check('Rumus CSV dinetralkan', App\Services\Csv::safe('=1+1') === "'=1+1" && App\Services\Csv::safe('-5') === '-5' && App\Services\Csv::safe('@x') === "'@x");
    $st = $db->prepare('INSERT INTO submissions (participation_id, post_id, post_code, posted_at, last_checked_at) VALUES (?, ?, ?, NOW(), NOW())');
    $subs = [];
    foreach ([0, 1, 2] as $n) {
        $st->execute([$p0, random_int(10 ** 15, 10 ** 16), substr(bin2hex(random_bytes(6)), 0, 11)]);
        $subs[] = (int) $db->lastInsertId();
    }
    $state2 = static fn (int $id): string => Submission::find($id)['status'];
    $check('Massal tanpa pilihan ditolak', $w->post('/admin/utas', ['keputusan' => 'setujui'], '/admin/utas')->status === 422);
    $check('Massal tolak tanpa alasan ditolak', $w->post('/admin/utas', ['keputusan' => 'tolak', 'ids' => [$subs[0]]], '/admin/utas')->status === 422 && $state2($subs[0]) === 'pending');
    $check('Massal menyetujui pilihan saja', $w->post('/admin/utas', ['keputusan' => 'setujui', 'ids' => [$subs[0], $subs[1], 'x']], '/admin/utas')->status === 303 && $state2($subs[0]) === 'approved' && $state2($subs[2]) === 'pending');
    $check('Massal tidak mengubah yang sudah diputus', $w->post('/admin/utas', ['keputusan' => 'tolak', 'alasan' => 'uji', 'ids' => [$subs[0], $subs[2]]], '/admin/utas')->status === 303 && $state2($subs[0]) === 'approved' && $state2($subs[2]) === 'rejected');
    $db->exec('DELETE FROM submissions WHERE id IN (' . implode(',', $subs) . ')');
    $check('Jeda dan lanjut campaign', $w->post("/admin/campaign/$campaign/status", ['tindakan' => 'jeda'], '/admin/pengaturan')->status === 303 && Campaign::find($campaign)['status'] === 'paused'
        && $w->post("/admin/campaign/$campaign/status", ['tindakan' => 'jeda'], '/admin/pengaturan')->status === 409 && $w->post("/admin/campaign/$campaign/status", ['tindakan' => 'lanjut'], '/admin/pengaturan')->status === 303 && Campaign::find($campaign)['status'] === 'active');
    $mail = "adm-new-$run@example.test";
    $team = ['name' => 'Baru', 'email' => $mail, 'password' => 'Panjang-sekali-1', 'konfirmasi' => $password];
    $check('Tambah admin butuh password sendiri', $w->post('/admin/tim', ['konfirmasi' => 'salah'] + $team, '/admin/tim')->status === 422 && User::findByEmail($mail) === null);
    $check('Email freelancer tidak bisa jadi admin', $w->post('/admin/tim', ['email' => "adm-3-$run@example.test"] + $team, '/admin/tim')->status === 422 && User::find($ids[3])['role'] === 'freelancer');
    $check('Password admin pendek ditolak', $w->post('/admin/tim', ['password' => 'pendek-1'] + $team, '/admin/tim')->status === 422);
    $check('Admin ditambahkan', $w->post('/admin/tim', $team, '/admin/tim')->status === 303 && User::findByEmail($mail)['role'] === 'admin');
    $new = (int) User::findByEmail($mail)['id'];
    $n = new Client($base);
    $n->post('/admin/masuk', ['email' => $mail, 'password' => 'Panjang-sekali-1'], '/admin/masuk');
    $check('Admin baru bisa masuk', $n->get('/admin')->status === 200);
    $check('Tidak bisa menonaktifkan diri', $w->post("/admin/tim/{$ids[6]}", ['tindakan' => 'nonaktif', 'konfirmasi' => $password], '/admin/tim')->status === 422);
    $check('Tindakan tim tidak menyentuh freelancer', $w->post("/admin/tim/{$ids[3]}", ['tindakan' => 'nonaktif', 'konfirmasi' => $password], '/admin/tim')->status === 422 && User::find($ids[3])['status'] === 'active');
    $check('Nonaktif memutus sesi', $w->post("/admin/tim/$new", ['tindakan' => 'nonaktif', 'konfirmasi' => $password], '/admin/tim')->status === 303 && $n->get('/admin')->status === 303);
    $check('Pengumuman kosong ditolak', $w->post('/admin/pengumuman', ['title' => 'x', 'body' => '', 'level' => 'info'], '/admin/pengumuman')->status === 422);
    $check('Pengumuman terbit', $w->post('/admin/pengumuman', ['title' => "Kabar $run", 'body' => 'Isi <b>tes</b>', 'level' => 'penting'], '/admin/pengumuman')->status === 303);
    $f->post('/masuk', ['email' => "adm-3-$run@example.test", 'password' => $password], '/masuk');
    $check('Freelancer melihat pengumuman', str_contains($f->get('/dashboard')->body, "Kabar $run") && str_contains($f->get('/pengumuman')->body, '&lt;b&gt;tes') && !str_contains($f->get('/dashboard')->body, "Kabar $run"));
    $note = (int) $db->query('SELECT MAX(id) FROM announcements')->fetchColumn();
    $check('Pengumuman diarsipkan', $w->post("/admin/pengumuman/$note", ['tindakan' => 'arsipkan'], '/admin/pengumuman')->status === 303 && !str_contains($f->get('/pengumuman')->body, "Kabar $run"));
    $db->exec("DELETE FROM announcements WHERE id = $note");
    $db->exec("DELETE FROM users WHERE id = $new");
    $check('Keluar admin', $w->post('/admin/keluar', [], '/admin')->status === 303 && $w->get('/admin')->status === 303);
    $w->post('/admin/masuk', ['email' => "adm-6-$run@example.test", 'password' => $password], '/admin/masuk');
    $check('Keputusan klik tanpa alasan ditolak', $w->post("/admin/akun/$p0", ['keputusan' => 'terima', 'alasan' => ''], '/admin/pengaturan')->status === 422);
} finally {
    $clear();
    $db->exec('DELETE FROM settings');
    foreach ($settings as $k => $x) $db->prepare('INSERT INTO settings VALUES (?, ?)')->execute([$k, $x]);
    $db->prepare('UPDATE scrape_state SET failures = ?, open_until = ? WHERE id = 1')->execute([$state['failures'], $state['open_until']]);
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in) OR target IN (" . implode(',', array_map(static fn (int $x): string => "'participation:$x'", $parts)) . ')');
    foreach (["ledger WHERE user_id IN ($in)", "payout_methods WHERE user_id IN ($in)", "valid_click_keys WHERE campaign_id = $campaign", "clicks WHERE campaign_id = $campaign"] as $q) $db->exec("DELETE FROM $q");
    $db->exec("DELETE s FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE p.campaign_id = $campaign");
    $db->exec("DELETE FROM participations WHERE campaign_id = $campaign");
    $db->exec("DELETE FROM campaigns WHERE id = $campaign");
    $db->exec("DELETE l FROM short_links l JOIN threads_accounts a ON a.id = l.account_id WHERE a.user_id IN ($in)");
    $db->exec("DELETE FROM threads_accounts WHERE user_id IN ($in)");
    $db->exec("DELETE d FROM devices d JOIN user_devices u ON u.device_id = d.id WHERE u.user_id IN ($in)");
    foreach ($devices as $x) $db->exec("DELETE FROM devices WHERE id = $x");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
