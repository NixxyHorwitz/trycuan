<?php
// Tes pengaturan, SEO, trafik, dokumen, mode campaign, dan login Google: php tests/site_test.php [base_url]
// Pengaturan dikembalikan dan data sementara dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\Campaign;
use App\Models\CampaignDoc;
use App\Models\Participation;
use App\Models\Setting;
use App\Models\ShortLink;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Models\Visit;
use App\Services\GoogleLogin;
use App\Services\Markdown;
use App\Services\ParticipationManager;
use App\Services\ProofFile;
use App\Services\SubmissionException;
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
    } catch (SubmissionException | VerificationException $e) {
        return $e->getMessage();
    }
    return '';
};

// Markdown
$md = static fn (string $t): string => (new Markdown('t-'))->render($t);
$check('HTML selalu di-escape', !str_contains($md("<script>x</script>\n\n## <img src=x>\n\n- <b>a</b>\n\n> <i>\n\n```\n</pre><script>\n```"), '<script>') && !str_contains($md('## <img src=x>'), '<img'));
$check('Link berbahaya tidak dibuat', !str_contains($md('[a](javascript:alert(1)) [b](//evil.test) [c](data:text/html,x)'), '<a '));
$check('Atribut link tidak bisa dipecah', !str_contains($md('[a](https://x.test/"onclick="y)'), '"onclick'));
$check('Penanda dalam tidak bisa dipalsukan', !str_contains($md("\x020\x03 `kode`"), "\x02"));
$check('Format dasar', str_contains($md("**tebal** `kode`\n\n1. satu\n2. dua\n\n- [x] cek"), '<strong>tebal</strong>') && str_contains($md("1. satu\n2. dua"), '<ol><li>') && str_contains($md('- [x] cek'), 'box on'));
$check('Teks aneh tidak macet', $md("#####x\n>\n```\ntanpa tutup") !== '' && $md(str_repeat("-\n# \n>> \n* *\n", 50)) !== '');

// Sumber kunjungan
foreach ([['https://www.google.com/search', '', 'Google'], ['https://l.threads.com/x', '', 'Threads'], ['https://t.co/x', '', 'X'], ['https://gemini.google.com/', '', 'Gemini'], ['https://blog.contoh.id/', '', 'blog.contoh.id'], ['', '', 'Langsung'], ['https://trycuan.test/', '', 'internal'],
    ['', 'instagram', 'Instagram'], ['https://notx.com/', '', 'notx.com'], ['', '<script>', 'script'], ['https://netflix.com/', '', 'netflix.com']] as [$ref, $utm, $want]) {
    $check("Sumber $ref$utm => $want", Visit::source($ref, $utm, 'trycuan.test') === $want);
}
$check('Jenis perangkat', Visit::device('Mozilla (iPhone; Mobile)') === 'mobile' && Visit::device('Mozilla (iPad)') === 'tablet' && Visit::device('Mozilla (Windows NT)') === 'desktop');

$db = Database::pdo();
$saved = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = [];
foreach (['a', 'b', 'c'] as $n) $ids[$n] = User::create("Tes $n", "site-$n-$run@example.test", $password);
$db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$ids['c']]);
$db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids['a'], (string) random_int(10 ** 15, 10 ** 16), "site.$run"]);
$acc = ThreadsAccount::forUser($ids['a']);
$firstVisit = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM visits')->fetchColumn();
$campaigns = [];
$make = static function (string $title, string $status = 'active') use (&$campaigns): int {
    return $campaigns[] = Campaign::create(['title' => $title, 'brief' => 'Brief rahasia anggota', 'destination_url' => 'https://example.com/', 'rules_json' => '{}', 'rate_per_1k' => 10000, 'cap_per_account' => 500000,
        'target_countries' => 'ID', 'budget' => 1000000, 'starts_at' => date('Y-m-d H:i:s', time() - 3600), 'ends_at' => date('Y-m-d H:i:s', time() + 86400), 'status' => $status]);
};
$clear = static function () use ($ids): void {
    foreach (['127.0.0.1', '::1'] as $ip) foreach (['login-ip', 'admin-login-ip', 'google', 'register'] as $k) RateLimiter::clear("$k:$ip");
    foreach ($ids as $id) RateLimiter::clear("password:$id");
};
$clear();

try {
    $admin = new Client($base);
    $admin->post('/admin/masuk', ['email' => "site-c-$run@example.test", 'password' => $password], '/admin/masuk');
    $free = new Client($base);
    $free->post('/masuk', ['email' => "site-a-$run@example.test", 'password' => $password], '/masuk');
    $guest = new Client($base);
    $form = static function (string $group, array $over = []): array {
        $data = [];
        foreach (Setting::groups()[$group][3] as $key => [$type]) if ($type !== 'image') $data[$key] = $type === 'secret' ? '' : Setting::str($key);
        return $over + $data;
    };
    $saveSettings = static fn (string $group, array $over): int => $admin->post((str_starts_with($group, 'seo-') ? '/admin/seo' : '/admin/pengaturan') . "?grup=$group", $form($group, $over), '/admin/akun-saya')->status;

    // Pengaturan
    foreach (array_keys(Setting::groups()) as $group) $check("Nilai bawaan $group tersimpan lagi", $saveSettings($group, []) === 303);
    $check('Nama situs dipakai', $saveSettings('umum', ['site_name' => "Situs $run"]) === 303 && str_contains($guest->get('/')->body, "Situs $run"));
    $check('Isian salah ditolak', $saveSettings('umum', ['site_name' => 'x']) === 422 && $saveSettings('umum', ['accent' => 'abu']) === 422 && $saveSettings('umum', ['contact_url' => 'javascript:alert(1)']) === 422 && $saveSettings('beranda', ['faq' => 'tanpa pemisah']) === 422);
    $check('Teks di-escape', $saveSettings('beranda', ['home_title' => '<b>Judul</b>']) === 303 && str_contains($guest->get('/')->body, '&lt;b&gt;Judul') && !str_contains($guest->body, '<b>Judul</b>'));
    $saveSettings('umum', ['register_open' => '0']);
    $check('Pendaftaran bisa ditutup', str_contains($guest->get('/daftar')->body, 'ditutup') && $guest->post('/daftar', ['name' => 'Coba', 'email' => "tutup-$run@example.test", 'password' => "Panjang-$run!", 'password_again' => "Panjang-$run!", 'terms' => '1'], '/masuk')->status === 422 && !User::findByEmail("tutup-$run@example.test"));
    $saveSettings('umum', ['register_open' => '1', 'maintenance' => '1']);
    $check('Mode perawatan', $guest->get('/')->status === 503 && $free->get('/dashboard')->status === 503 && $admin->get('/admin')->status === 200 && $guest->get('/robots.txt')->status === 200);
    $saveSettings('umum', ['maintenance' => '0']);
    $check('Perawatan selesai', $guest->get('/')->status === 200);

    // Avatar dan profil
    $seed = bin2hex(random_bytes(8));
    $check('Avatar tersimpan', $free->post('/profil', ['name' => 'Tes a', 'avatar_style' => 'blobs', 'avatar_seed' => $seed], '/profil')->status === 303 && User::find($ids['a'])['avatar_seed'] === $seed && str_contains($free->get('/profil')->body, "blobs/svg?seed=$seed"));
    $free->post('/profil', ['name' => 'Tes a', 'avatar_style' => '../x', 'avatar_seed' => '"><x'], '/profil');
    $free->get('/profil');
    $check('Avatar aneh diabaikan', User::find($ids['a'])['avatar_style'] === 'blobs' && User::find($ids['a'])['avatar_seed'] === $seed);
    $check('Email tidak dikirim ke CDN', !preg_match('~dicebear[^"]*(example\.test|' . $run . ')~', $free->body));
    $check('Menu akun dan tanpa profil di navigasi', str_contains($free->body, 'class="menu"') && !preg_match('~<nav class="dock".*?/profil.*?</nav>~s', $free->body));
    $other = new Client($base);
    $other->post('/masuk', ['email' => "site-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Keluarkan perangkat lain', $free->post('/profil/sesi', [], '/profil/keamanan')->status === 303 && $free->get('/dashboard')->status === 200 && $other->get('/dashboard')->status === 303);

    // Mode campaign
    $one = $make("Satu $run");
    $two = $make("Dua $run");
    $draft = $make("Draf $run", 'draft');
    $m = new ParticipationManager();
    $saveSettings('campaign', ['campaign_mode' => 'one']);
    Setting::set('campaign_mode', 'one');
    $m->join($acc, $one);
    $check('Mode satu per akun', str_contains($error(fn () => $m->join($acc, $two)), 'masih mengikuti'));
    $check('Campaign utama wajib aktif', $saveSettings('campaign', ['campaign_mode' => 'focus', 'campaign_focus' => (string) $draft]) === 422 && $saveSettings('campaign', ['campaign_mode' => 'focus', 'campaign_focus' => '0']) === 422);
    Setting::set('campaign_mode', 'focus');
    Setting::set('campaign_focus', $two);
    $check('Mode fokus hanya menawarkan campaign utama', !str_contains($guest->get('/program')->body, "Satu $run") && str_contains($guest->body, "Dua $run") && str_contains($free->get('/campaign')->body, "Satu $run") && $guest->get("/program/$one")->status === 404);
    $check('Mode fokus tetap satu per akun', $error(fn () => $m->join($acc, $two)) !== '');
    Setting::set('campaign_mode', 'multi');
    $check('Mode multi boleh ikut lagi', $error(fn () => $m->join($acc, $two)) === '' && count(Participation::running((int) $acc['id'])) === 2);
    $code = ShortLink::forAccount((int) $acc['id'])['code'];
    $check('Link pendek meminta pilihan', ShortLink::resolve($code)['campaign'] === null && str_contains($guest->get("/$code")->body, "/$code?c=$two") && (int) ShortLink::resolve($code, $two)['campaign']['id'] === $two && str_contains($guest->get("/$code?c=$one")->body, 'data-token'));
    $check('Pilihan asing tidak aktif', ShortLink::resolve($code, 999999999)['campaign'] === null);
    $check('Beranda bisa berpindah campaign', str_contains($free->get("/dashboard?campaign=$one")->body, "/dashboard?campaign=$two") && str_contains($free->body, 'account-card'));

    // Publik dan SEO
    $check('Brief tidak terbuka ke publik', $guest->get("/program/$one")->status === 200 && !str_contains($guest->body, 'Brief rahasia'));
    $home = $guest->get('/')->body;
    foreach (['og:title', 'og:image', 'twitter:card', 'rel="canonical"', 'application/ld+json', 'FAQPage', 'max-image-preview'] as $part) $check("Beranda memuat $part", str_contains($home, $part));
    preg_match('~ld\+json">(.*?)</script>~s', $home, $json);
    $check('Data terstruktur sah', isset(json_decode($json[1] ?? '', true)['@graph']));
    $check('Halaman anggota tidak diindeks', str_contains($free->get('/dashboard')->body, 'noindex') && !str_contains($free->body, 'ld+json') && str_contains($admin->get('/admin')->body, 'noindex'));
    Setting::set('faq', 'A</script><script>x | B');
    $check('JSON-LD tidak bisa ditutup dari isi', !str_contains($guest->get('/')->body, '</script><script>x'));
    $map = $guest->get('/sitemap.xml')->body;
    $check('Sitemap sah dan tanpa halaman pribadi', simplexml_load_string($map) !== false && str_contains($map, "/program/$one") && !str_contains($map, "/program/$draft") && !str_contains($map, 'dashboard'));
    $check('Robots dan manifest', str_contains($guest->get('/robots.txt')->body, 'Sitemap:') && json_decode($guest->get('/site.webmanifest')->body) !== null);
    Setting::set('seo_index', 0);
    $check('Indeks bisa dimatikan', str_contains($guest->get('/robots.txt')->body, "Disallow: /\n") && str_contains($guest->get('/')->body, 'noindex'));
    Setting::set('public_campaigns', 0);
    $check('Daftar publik bisa ditutup', $guest->get('/program')->status === 404 && $guest->get("/program/$one")->status === 404);

    // Dokumen
    $new = "/admin/campaign/$one/dokumen/baru";
    $doc = ['kind' => 'panduan', 'title' => "Panduan $run", 'body' => "## Langkah\n\nIsi <script>alert(1)</script>", 'status' => 'published', 'tindakan' => 'simpan'];
    $check('Dokumen tanpa judul ditolak', $admin->post($new, ['title' => ''] + $doc, $new)->status === 422);
    $check('Dokumen tersimpan', $admin->post($new, $doc, $new)->status === 303 && $admin->post($new, ['title' => "Draf dok $run", 'status' => 'draft'] + $doc, $new)->status === 303);
    $page = $free->get("/campaign/$one/panduan");
    $check('Anggota membaca dokumen terbit saja', $page->status === 200 && str_contains($page->body, "Panduan $run") && !str_contains($page->body, "Draf dok $run") && !str_contains($page->body, '<script>alert'));
    $check('Campaign draf tidak terbuka', $free->get("/campaign/$draft/panduan")->status === 404);
    $file = tempnam(sys_get_temp_dir(), 'tc');
    imagepng(imagecreatetruecolor(8, 8), $file);
    $check('Berkas diunggah', $admin->post("/admin/campaign/$one/berkas", ['berkas' => new CURLFile($file, 'image/png', '../x<y>.php')], $new, true)->status === 303);
    $row = CampaignDoc::files($one)[0] ?? null;
    $check('Nama berkas dirapikan', $row !== null && str_ends_with($row['name'], '.png') && !preg_match('/[<>\/]/', $row['name']));
    $check('Berkas hanya untuk anggota', $free->get("/materi/{$row['id']}")->status === 200 && str_contains($free->headers, 'image/png') && $guest->get("/materi/{$row['id']}")->status === 303);
    file_put_contents($file, '<?php echo 1;');
    $check('Skrip ditolak', $admin->post("/admin/campaign/$one/berkas", ['berkas' => new CURLFile($file, 'image/png', 'x.png')], $new, true)->status === 422);
    $check('Logo berisi skrip ditolak', $admin->post('/admin/pengaturan?grup=umum', $form('umum') + ['site_logo' => new CURLFile($file, 'image/png', 'x.png')], '/admin/akun-saya', true)->status === 422);
    unlink($file);
    $check('Pratinjau di-escape', str_contains($admin->post('/admin/dokumen/pratinjau', ['body' => '<script>'], $new)->body, '&lt;script'));
    $check('Berkas dihapus', $admin->post("/admin/materi/{$row['id']}", [], $new)->status === 303 && !is_file(ProofFile::path($row['file'])));
    $check('Halaman utas', $free->get('/utas')->status === 200 && $free->get('/utas?status[]=x&halaman=-1&campaign=abc')->status === 200);

    // Trafik
    $before = (int) $db->query('SELECT COUNT(*) FROM visits')->fetchColumn();
    $visit = static function (string $path, string $agent, array $headers = []) use ($base): void {
        $curl = curl_init($base . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => $agent, CURLOPT_HTTPHEADER => $headers]);
        curl_exec($curl);
    };
    $human = 'Mozilla/5.0 (Linux; Android 14; Mobile) Chrome/130.0 Safari/537.36';
    $visit('/panduan?utm_source=tiktok&utm_campaign=uji', $human);
    $last = $db->query('SELECT * FROM visits ORDER BY id DESC LIMIT 1')->fetch();
    $check('Kunjungan tercatat', $last['path'] === '/panduan' && $last['source'] === 'TikTok' && $last['campaign'] === 'uji' && $last['device'] === 'mobile' && $last['os'] === 'Android');
    $visit('/panduan', 'Googlebot/2.1');
    $visit('/panduan', $human, ['X-Sec-Purpose: prefetch']);
    $visit('/admin/masuk', $human);
    $visit('/tidak-ada', $human);
    $visit('/robots.txt', $human);
    $check('Bot, prefetch, admin, dan error tidak dihitung', (int) $db->query('SELECT COUNT(*) FROM visits')->fetchColumn() === $before + 1);
    $check('Laporan trafik', Visit::report(7, true)['now']['views'] >= 1 && $admin->get('/admin/trafik?hari=30')->status === 200 && $admin->get('/admin/trafik/ekspor')->status === 200);

    // Login Google
    $check('Google nonaktif', $guest->get('/masuk/google')->status === 404 && !str_contains($guest->get('/masuk')->body, '/masuk/google'));
    $check('Aktif tanpa kunci ditolak', $saveSettings('google', ['google_on' => '1']) === 422);
    $id = "123-$run.apps.googleusercontent.com";
    $check('Kunci tersimpan terenkripsi', $saveSettings('google', ['google_on' => '1', 'google_id' => $id, 'google_secret' => "rahasia-$run"]) === 303);
    Setting::set('google_on', 1);
    $check('Secret tidak terbaca di database, halaman, atau audit', Setting::secret('google_secret') === "rahasia-$run" && !str_contains(Setting::str('google_secret'), $run) && !str_contains($admin->get('/admin/pengaturan?grup=google')->body, "rahasia-$run") && !str_contains((string) $db->query("SELECT GROUP_CONCAT(meta_json) FROM audit_logs WHERE target = 'settings:google'")->fetchColumn(), "rahasia-$run"));
    $check('Kosong mempertahankan secret', $saveSettings('google', ['google_on' => '1']) === 303 && Setting::secret('google_secret') === "rahasia-$run");
    $start = $guest->get('/masuk/google');
    $check('Diarahkan ke Google dengan PKCE', $start->status === 303 && str_contains($start->headers, 'https://accounts.google.com/') && str_contains($start->headers, 'code_challenge_method=S256') && !str_contains($start->headers, "rahasia-$run"));
    $check('State palsu ditolak', $guest->get('/masuk/google/selesai?state=x&code=y')->status === 303 && str_contains($guest->get('/masuk')->body, 'berakhir') && $guest->get('/dashboard')->status === 303);
    [, $flow] = GoogleLogin::begin(null);
    $claims = ['iss' => 'https://accounts.google.com', 'aud' => $id, 'exp' => time() + 600, 'nonce' => $flow['nonce'], 'sub' => "9$run", 'email' => "Google-$run@example.test", 'email_verified' => true, 'name' => 'Nama Google'];
    $identity = static function (array $over = [], ?string $state = null) use ($claims, $flow): array {
        $b = static fn (array $x): string => rtrim(strtr(base64_encode(json_encode($x)), '+/', '-_'), '=');
        return (new GoogleLogin(static fn (array $f): array => ['id_token' => $b(['alg' => 'RS256']) . '.' . $b($over + $claims) . '.x']))->identity($flow, $state ?? $flow['state'], 'kode');
    };
    foreach (['iss' => 'https://evil.test', 'aud' => 'lain', 'exp' => time() - 1, 'nonce' => 'lain', 'email_verified' => false, 'sub' => '../x', 'email' => 'bukan email'] as $key => $bad) $check("Klaim $key salah ditolak", $error(fn () => $identity([$key => $bad])) !== '');
    $check('State berbeda ditolak', $error(fn () => $identity([], 'lain')) !== '');
    $google = $identity();
    $user = GoogleLogin::resolve($google);
    $ids['g'] = (int) $user['id'];
    $check('Pendaftaran Google', $user['role'] === 'freelancer' && (int) $user['has_password'] === 0 && $user['email'] === "google-$run@example.test");
    $check('Masuk lagi memakai akun yang sama', (int) GoogleLogin::resolve($google)['id'] === $ids['g']);
    $check('Email akun lama tidak disambungkan diam-diam', str_contains($error(fn () => GoogleLogin::resolve(['sub' => "8$run", 'email' => "site-b-$run@example.test", 'name' => 'x'])), 'sudah punya akun') && User::find($ids['b'])['google_sub'] === null);
    $check('Email admin tidak bisa masuk lewat Google', $error(fn () => GoogleLogin::resolve(['sub' => "7$run", 'email' => "site-c-$run@example.test", 'name' => 'x'])) !== '');
    $check('Akun Google tidak bisa dipakai dua kali', str_contains($error(fn () => GoogleLogin::link($ids['b'], $google)), 'akun lain'));
    $db->exec("UPDATE users SET status = 'blocked' WHERE id = {$ids['g']}");
    $check('Akun diblokir ditolak', str_contains($error(fn () => GoogleLogin::resolve($google)), 'diblokir'));
    GoogleLogin::link($ids['a'], ['sub' => "6$run"]);
    $check('Lepas Google butuh password', $free->post('/profil/google', ['konfirmasi' => 'salah'], '/profil/keamanan')->status === 422 && $free->post('/profil/google', ['konfirmasi' => $password], '/profil/keamanan')->status === 303 && User::find($ids['a'])['google_sub'] === null);
} finally {
    $clear();
    $db->exec('DELETE FROM settings');
    $st = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($saved as $key => $value) $st->execute([$key, $value]);
    $in = implode(',', array_map('intval', $ids));
    $cin = implode(',', $campaigns) ?: '0';
    foreach ($db->query("SELECT file FROM campaign_files WHERE campaign_id IN ($cin)")->fetchAll(PDO::FETCH_COLUMN) as $file) ProofFile::delete($file);
    $db->exec("DELETE FROM participations WHERE campaign_id IN ($cin)");
    $db->exec("DELETE FROM campaigns WHERE id IN ($cin)");
    $db->exec("DELETE FROM visits WHERE id > $firstVisit AND (campaign = 'uji' OR created_at > NOW() - INTERVAL 10 MINUTE)");
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in)");
    $db->exec("DELETE l FROM short_links l JOIN threads_accounts a ON a.id = l.account_id WHERE a.user_id IN ($in)");
    $db->exec("DELETE FROM threads_accounts WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
