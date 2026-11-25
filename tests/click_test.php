<?php
// Tes shortlink dan filter klik: php tests/click_test.php [base_url]
// Memakai data sementara; dihapus di akhir.
declare(strict_types=1);

use App\Core\ClientIp;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Router;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\Device;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\Clicks\Challenge;
use App\Services\Clicks\ClickFilter;
use App\Services\Clicks\ClickRequest;
use App\Services\Clicks\ClickTracker;
use App\Services\Clicks\Token;
use App\Services\Clicks\Verdict;
use App\Services\LinkVerifier;
use App\Services\ParticipationManager;
use App\Services\Threads\Outcome;
use App\Services\Threads\PostData;
use App\Services\Threads\ProfileData;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsSource;
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

final class FakeChallenge implements Challenge
{
    public function __construct(public string $result = 'pass')
    {
    }

    public function verify(string $response, string $ip): string
    {
        return $this->result;
    }
}

const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';
const SIGNALS = ['webdriver' => false, 'platform' => 'Linux armv81', 'lang' => 'id-ID', 'tz' => 'Asia/Jakarta', 'w' => 412, 'h' => 915, 'touch' => 5, 'cores' => 8];

function click(array $over = []): ClickRequest
{
    return new ClickRequest(...array_merge([
        'ip' => '203.0.113.5', 'cookie' => null, 'userAgent' => PHONE, 'acceptLanguage' => 'id-ID,id;q=0.9,en;q=0.8', 'fetchSite' => 'same-origin',
        'purpose' => '', 'country' => null, 'token' => '', 'challenge' => 'x', 'signals' => SIGNALS,
    ], $over));
}

// Pekerja untuk tes paralel
if (($argv[1] ?? '') === '--worker') {
    [, , $code, $cookie, $token] = $argv;
    echo (new ClickTracker(new FakeChallenge()))->record($code, click(['cookie' => $cookie, 'token' => $token])) ?? 'null';
    exit;
}

$base = local_base($argv[1] ?? 'http://trycuan.test');
$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};

// Pencocokan link
$host = ShortLink::host();
foreach ([
    ["https://$host/aB3dEfG", true], ["http://www.$host/aB3dEfG/", true], ["https://$host/aB3dEfG?x=1", true],
    ["https://$host/ab3defg", false], ["https://$host/aB3dEfGh", false], ["https://$host/x/aB3dEfG", false], ["https://$host.evil.test/aB3dEfG", false],
    ["https://evil$host/aB3dEfG", false], ["https://$host@evil.test/aB3dEfG", false], ["https://evil.test/?u=https://$host/aB3dEfG", false], ["javascript://$host/aB3dEfG", false],
] as [$url, $want]) $check("Link cocok=" . ($want ? 'ya' : 'tidak') . " $url", LinkVerifier::matches($url, 'aB3dEfG') === $want);
foreach (['/aB3dEfG' => true, '/aB3dEfG/klik' => true, '/aB3dEfG?a=b' => true, '/privasi' => false, '/campaign/1' => false, '/aB3dEfG/admin' => false, '/ABCDEFG' => false] as $uri => $want) {
    $check("Route shortlink $uri", Router::isShortLink($uri) === $want);
}

// Token dan cookie
$ck = Device::create();
$t = Token::make('aB3dEfG', 5, $ck);
$check('Cookie bertanda tangan', Device::valid($ck) && !Device::valid(str_repeat('a', 32) . '.' . str_repeat('b', 32)));
$check('Token sah', Token::check($t, 'aB3dEfG', $ck) === 5);
$check('Token link lain ditolak', Token::check($t, 'aB3dEfH', $ck) === null);
$check('Token perangkat lain ditolak', Token::check($t, 'aB3dEfG', Device::create()) === null);
$check('Token diubah ditolak', Token::check(str_replace('.5.', '.6.', $t), 'aB3dEfG', $ck) === null);
$check('Token kedaluwarsa ditolak', Token::check(Token::make('aB3dEfG', 5, $ck, time() - Token::TTL - 1), 'aB3dEfG', $ck) === null);
$check('Token masa depan ditolak', Token::check(Token::make('aB3dEfG', 5, $ck, time() + 60), 'aB3dEfG', $ck) === null);

// Lapis 1, 3, 4, 6 tanpa database
$list = ROOT . '/storage/test-hosting-' . bin2hex(random_bytes(4)) . '.txt';
file_put_contents($list, "# uji\n198.51.100.0/24 # contoh\n2001:db8::/32\n");
$filter = new ClickFilter($list);
$one = static function (string $layer, mixed ...$args) use ($filter): Verdict {
    $v = new Verdict();
    $filter->$layer(...[...$args, $v]);
    return $v;
};
$sig = static fn (array $s, array $o = []): ClickRequest => click(['signals' => array_merge(SIGNALS, $s)] + $o);
$expect = static function (string $label, Verdict $v, string $want) use ($check): void {
    $check("$label => $want", $v->status() === $want);
};
$expect('HP biasa', $one('request', click()), 'valid');
foreach (['', 'facebookexternalhit/1.1', 'Mozilla/5.0 HeadlessChrome/130', 'curl/8', 'WhatsApp/2', 'Googlebot/2.1'] as $ua) $expect("UA [$ua]", $one('request', click(['userAgent' => $ua])), 'invalid');
$expect('Prefetch', $one('request', click(['purpose' => 'prefetch'])), 'invalid');
$expect('Lintas situs', $one('request', click(['fetchSite' => 'cross-site'])), 'invalid');
foreach (['pass' => 'valid', 'fail' => 'invalid', 'missing' => 'held', 'unavailable' => 'held'] as $result => $want) $expect("Tantangan $result", $one('challenge', $result), $want);
$expect('Browser biasa', $one('browser', click()), 'valid');
$expect('Webdriver', $one('browser', $sig(['webdriver' => true])), 'invalid');
$expect('HP tanpa sentuh', $one('browser', $sig(['touch' => 0])), 'held');
$expect('Dua sinyal kuat', $one('browser', $sig(['touch' => 0, 'platform' => 'Win32'])), 'invalid');
$expect('Tanpa sinyal sama sekali', $one('browser', click(['signals' => []])), 'invalid');
$expect('Satu sinyal lemah', $one('browser', $sig(['tz' => 'UTC'])), 'valid');
$expect('iPhone', $one('browser', $sig(['platform' => 'iPhone'], ['userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15'])), 'valid');
$expect('Tipe data aneh tidak error', $one('browser', click(['signals' => ['w' => [], 'lang' => ['x'], 'touch' => 'a']])), 'invalid');
$expect('IP biasa', $one('network', click(), ['ID']), 'valid');
$expect('IP hosting', $one('network', click(['ip' => '198.51.100.9']), ['ID']), 'held');
$expect('IPv6 hosting', $one('network', click(['ip' => '2001:db8:1::1']), ['ID']), 'held');
unlink($list);
$check('CIDR batas bit', ClientIp::inCidr('10.0.0.127', '10.0.0.0/25') && !ClientIp::inCidr('10.0.0.128', '10.0.0.0/25') && !ClientIp::inCidr('10.0.0.1', 'ngawur/8'));
$check('IPv6 satu /64 dianggap sama', ClientIp::hash('2001:db8::1') === ClientIp::hash('2001:db8::ffff') && ClientIp::hash('2001:db8::1') !== ClientIp::hash('2001:db8:0:1::1'));
$check('Jarak teratur terdeteksi', ClickFilter::tooRegular([80, 70, 60, 50, 40, 30, 20, 10]) && !ClickFilter::tooRegular([300, 250, 240, 100, 90, 30, 25, 1]) && !ClickFilter::tooRegular([20, 10]));

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$ids = $accounts = $links = $cookies = [];
foreach (['a', 'b', 'c', 'd'] as $n) {
    $ids[$n] = User::create("Tes $n", "click-$n-$run@example.test", $password);
    $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')->execute([$ids[$n], (string) random_int(10 ** 15, 10 ** 16), "klik.$n"]);
    $accounts[$n] = ThreadsAccount::forUser($ids[$n]);
    $links[$n] = ShortLink::forAccount((int) $accounts[$n]['id']);
}
$campaign = Campaign::create(['title' => "Klik $run", 'brief' => 'x', 'destination_url' => 'https://example.com/tujuan', 'rules_json' => '{}', 'rate_per_1k' => 10000,
    'cap_per_account' => 100000, 'target_countries' => 'ID', 'budget' => 1000000, 'starts_at' => date('Y-m-d H:i:s', time() - 3600), 'ends_at' => date('Y-m-d H:i:s', time() + 86400), 'status' => 'active']);
$m = new ParticipationManager();
foreach ($accounts as $x) $m->join($x, $campaign);
$clear = static function () use ($ids): void {
    foreach (['127.0.0.1', '::1'] as $ip) {
        RateLimiter::clear("login-ip:$ip");
        RateLimiter::clear("click:$ip");
    }
    foreach ($ids as $id) foreach (['gap', 'check'] as $k) RateLimiter::clear("link-$k:$id");
};
$clear();
$wipe = static function () use ($db, $campaign): void {
    $db->exec("DELETE FROM valid_click_keys WHERE campaign_id = $campaign");
    $db->exec("DELETE FROM clicks WHERE campaign_id = $campaign");
};

try {
    // Link di bio
    $a = $accounts['a'];
    $src = new FakeSource();
    $lv = new LinkVerifier($src);
    $linkCheck = static function () use ($lv, $a): string {
        try {
            $lv->check($a);
        } catch (VerificationException $e) {
            return $e->getMessage();
        } catch (ThreadsException $e) {
            return $e->outcome->value;
        }
        return '';
    };
    $bio = static fn (array $urls, ?string $pk = null, bool $private = false): ProfileData => new ProfileData($pk ?? $a['threads_pk'], 'klik.a', '', $urls, $private);
    $at = static fn () => ShortLink::forAccount((int) $a['id'])['link_verified_at'];
    $mine = 'https://' . ShortLink::url($links['a']);
    $check('Format kode', preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Za-z0-9]{7}$/', $links['a']['code']) === 1 && ShortLink::forAccount((int) $a['id'])['code'] === $links['a']['code']);
    $src->next = $bio([]);
    $check('Tanpa link ditolak', str_contains($linkCheck(), 'belum ditemukan') && $at() === null);
    $src->next = $bio(['https://' . ShortLink::url($links['b'])]);
    $check('Link akun lain ditolak', $linkCheck() !== '');
    $src->next = $bio([$mine], '999');
    $check('Username dipakai akun lain ditolak', str_contains($linkCheck(), 'akun lain') && $at() === null);
    $src->next = $bio([$mine], null, true);
    $check('Akun privat ditolak', str_contains($linkCheck(), 'privat'));
    $src->next = $bio(['https://example.com', $mine]);
    $check('Link terdeteksi di antara link lain', $linkCheck() === '' && $at() !== null);
    $db->exec("UPDATE short_links SET link_verified_at = '2026-01-01 00:00:00' WHERE id = {$links['a']['id']}");
    $linkCheck();
    $check('Cek ulang tidak menggeser waktu pasang', $at() === '2026-01-01 00:00:00');
    $src->next = new ThreadsException(Outcome::Network);
    $check('Gagal jaringan tidak melepas link', $linkCheck() === 'network' && $at() === '2026-01-01 00:00:00');
    $src->next = $bio([]);
    $linkCheck();
    $check('Link hilang tercatat', $at() === null && ShortLink::forAccount((int) $a['id'])['lost_at'] !== null);
    $src->next = $bio([$mine]);
    $linkCheck();
    $check('Pasang ulang dihitung dari awal', $at() > '2026-01-02' && ShortLink::forAccount((int) $a['id'])['lost_at'] === null);

    // Pencatatan klik
    $chal = new FakeChallenge();
    $tracker = new ClickTracker($chal, new ClickFilter('/tidak/ada'));
    $n = 0;
    $hit = static function (array $o = [], string $link = 'a', ?string $cookie = null) use ($tracker, $links, $campaign, &$n, &$cookies): ?string {
        $cookies[] = $cookie ??= Device::create();
        $n++;
        // Bawaan: IP dan ciri berbeda supaya tiap tes hanya menyentuh satu lapis.
        return $tracker->record($links[$link]['code'], click($o + ['cookie' => $cookie, 'token' => Token::make($links[$link]['code'], $campaign, $cookie),
            'ip' => '100.' . ($n >> 8) . '.' . ($n & 255) . '.1', 'signals' => ['w' => 300 + $n] + SIGNALS]));
    };
    $reasons = static fn (): array => json_decode((string) $db->query("SELECT reasons_json FROM clicks WHERE campaign_id = $campaign ORDER BY id DESC LIMIT 1")->fetchColumn(), true);
    $count = static fn (string $link = 'a'): int => Click::validCount((int) $links[$link]['id'], $campaign);

    $c1 = Device::create();
    $check('Klik wajar valid', $hit([], 'a', $c1) === 'valid' && $count() === 1);
    $check('Perangkat sama tidak dihitung lagi', $hit([], 'a', $c1) === 'invalid' && $reasons() === ['perangkat_sama'] && $count() === 1);
    $tok = Token::make($links['a']['code'], $campaign, $c1);
    $tracker->record($links['a']['code'], click(['cookie' => $c1, 'token' => $tok]));
    $total = $db->query("SELECT COUNT(*) FROM clicks WHERE campaign_id = $campaign")->fetchColumn();
    $check('Token hanya sekali pakai', $tracker->record($links['a']['code'], click(['cookie' => $c1, 'token' => $tok])) === null);
    $check('Tanpa cookie tidak dicatat', $tracker->record($links['a']['code'], click(['token' => $tok])) === null);
    $check('Token palsu tidak dicatat', $hit(['token' => 'x']) === null && $total === $db->query("SELECT COUNT(*) FROM clicks WHERE campaign_id = $campaign")->fetchColumn());
    $wipe();

    foreach (range(1, 3) as $i) $shared[] = $hit(['ip' => '203.0.113.77']);
    $check('IP bersama beda perangkat tetap valid', $shared === ['valid', 'valid', 'valid']);
    $check('Cookie baru dengan ciri dan IP sama ditahan', $hit(['ip' => '203.0.113.77', 'signals' => ['w' => 300 + $n] + SIGNALS]) === 'held' && $reasons() === ['ciri_dan_ip_sama']);
    $wipe();
    $chal->result = 'unavailable';
    $c2 = Device::create();
    $check('Turnstile mati: ditahan', $hit([], 'a', $c2) === 'held' && $count() === 0);
    $chal->result = 'pass';
    $check('Klik ditahan tidak memakan jatah perangkat', $hit([], 'a', $c2) === 'valid');
    $wipe();

    $own = Device::create();
    $link = static fn (int $user, string $cookie, string $ip) => $db->prepare('INSERT INTO user_devices (user_id, device_id, ip_hash, last_login_at) VALUES (?, ?, ?, NOW())')
        ->execute([$user, Device::touch($cookie), ClientIp::hash($ip)]);
    $cookies[] = $own;
    $link($ids['a'], $own, '203.0.113.200');
    $check('Perangkat pemilik dibuang', $hit([], 'a', $own) === 'invalid' && in_array('perangkat_pemilik', $reasons(), true));
    $check('Perangkat freelancer lain ditahan', $hit([], 'b', $own) === 'held' && $reasons() === ['perangkat_freelancer_lain']);
    $check('IP pemilik hanya sinyal', $hit(['ip' => '203.0.113.200']) === 'valid' && $reasons() === ['ip_pemilik']);
    $check('IP pemilik ditambah sinyal lain ditahan', $hit(['ip' => '203.0.113.200', 'signals' => ['w' => 999, 'tz' => 'UTC'] + SIGNALS]) === 'held');
    $wipe();

    $cl = Device::create();
    $check('Dua akun masih wajar', $hit([], 'a', $cl) === 'valid' && $hit([], 'b', $cl) === 'valid');
    $check('Akun ketiga ditahan', $hit([], 'c', $cl) === 'held' && $reasons() === ['lintas_akun']);
    $check('Klik lama cluster ikut ditahan', $count('a') === 0 && $count('b') === 0);
    $wipe();

    $seed = static function (int $total, string $ip, string $trait, string $ago) use ($db, $links, $campaign): void {
        for ($i = 0; $i < $total; $i++) {
            $db->prepare("INSERT INTO clicks (short_link_id, campaign_id, ip_hash, subnet_hash, trait_hash, device_type, risk_score, status, reasons_json, created_at)
                VALUES (?, ?, ?, ?, ?, 'mobile', 0, 'valid', '[]', NOW() - INTERVAL ? SECOND)")->execute([$links['a']['id'], $campaign,
                ClientIp::hash($ip === '' ? "10.9.$i.1" : $ip), ClientIp::subnetHash($ip === '' ? "10.9.$i.1" : $ip), $trait === '' ? str_pad((string) $i, 64, '0') : $trait, (int) $ago + ($i * $i * 7)]);
        }
    };
    $seed(ClickFilter::BURST_PER_MINUTE, '', '', '0');
    $db->exec("UPDATE clicks SET created_at = NOW() WHERE campaign_id = $campaign");
    $check('Lonjakan ditahan', $hit() === 'held' && in_array('lonjakan', $reasons(), true));
    $wipe();
    $seed(ClickFilter::IP_PER_DAY, '203.0.113.50', '', '600');
    $check('Volume satu IP ditahan', $hit(['ip' => '203.0.113.50']) === 'held' && $reasons() === ['volume_ip']);
    $check('IP lain tidak terdampak', $hit() === 'valid');
    $wipe();
    $traits = hash_hmac('sha256', 'traits:' . click()->traits(), App\Core\Env::get('APP_KEY'));
    $seed(20, '', $traits, '600');
    $check('Perangkat seragam ditahan', $hit(['signals' => SIGNALS]) === 'held' && $reasons() === ['perangkat_seragam']);
    $wipe();

    // Paralel: satu perangkat, enam token berbeda
    $cp = Device::create();
    $cookies[] = $cp;
    $procs = [];
    for ($i = 0; $i < 6; $i++) {
        $procs[] = popen('"' . PHP_BINARY . '" "' . __FILE__ . '" --worker ' . implode(' ', [$links['d']['code'], $cp, Token::make($links['d']['code'], $campaign, $cp)]), 'r');
    }
    $out = [];
    foreach ($procs as $p) {
        $out[] = trim((string) stream_get_contents($p));
        pclose($p);
    }
    sort($out);
    $check('Klik paralel hanya satu yang valid', array_count_values($out) === ['invalid' => 5, 'valid' => 1] && $count('d') === 1);
    $wipe();

    // HTTP
    $w = new Client($base);
    $code = $links['a']['code'];
    $w->get("/$code");
    $check('Halaman perantara', $w->status === 200 && str_contains($w->body, 'https://example.com/tujuan') && str_contains($w->body, '/privasi') && stripos($w->headers, 'noindex') !== false);
    $check('Tanpa cookie sesi, dengan cookie perangkat', !str_contains($w->headers, 'trycuan_session') && str_contains($w->headers, 'trycuan_device') && stripos($w->headers, 'httponly') !== false);
    $check('Kode beda huruf 404', $w->get('/' . strtr($code, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') . '')->status === 404);
    $check('Beacon kosong aman', $w->post("/$code/klik", ['x' => 1])->status === 204 && (int) $db->query("SELECT COUNT(*) FROM clicks WHERE campaign_id = $campaign")->fetchColumn() === 0);
    $check('Beacon lewat GET ditolak', $w->get("/$code/klik")->status === 405);
    $check('Halaman privasi', $w->get('/privasi')->status === 200);
    $db->exec("UPDATE campaigns SET status = 'paused' WHERE id = $campaign");
    $check('Campaign dijeda: link tidak aktif', $w->get("/$code")->status === 200 && !str_contains($w->body, 'example.com/tujuan'));
    $db->exec("UPDATE campaigns SET status = 'active' WHERE id = $campaign");
    $db->exec("UPDATE users SET status = 'blocked' WHERE id = {$ids['a']}");
    $check('User diblokir: link 404', $w->get("/$code")->status === 404);
    $db->exec("UPDATE users SET status = 'active' WHERE id = {$ids['a']}");

    $u = new Client($base);
    $u->post('/masuk', ['email' => "click-a-$run@example.test", 'password' => $password], '/masuk');
    $check('Link tampil di akun', str_contains($u->get('/akun-threads')->body, ShortLink::url($links['a'])) && str_contains($u->body, 'Klik valid'));
    $check('Perangkat login tercatat', (int) $db->query("SELECT COUNT(*) FROM user_devices WHERE user_id = {$ids['a']}")->fetchColumn() === 2);
    RateLimiter::hit("link-gap:{$ids['a']}", 1, 10);
    $check('Jeda cek link berlaku', $u->post('/akun-threads/link', [], '/akun-threads')->status === 429 && $u->get('/akun-threads/link')->status === 200);
    $check('Log klik khusus admin', $u->get('/admin/klik')->status === 403);
    $db->exec("UPDATE users SET role = 'admin' WHERE id = {$ids['a']}");
    $hit(['userAgent' => 'curl/8']);
    $check('Log klik admin', $u->get('/admin/klik')->status === 200 && str_contains($u->body, 'Tidak valid'));
} finally {
    $clear();
    $wipe();
    $in = implode(',', array_map('intval', $ids));
    $ain = implode(',', array_map(static fn (array $x): int => (int) $x['id'], $accounts));
    $db->exec("DELETE FROM participations WHERE campaign_id = $campaign");
    $db->exec("DELETE FROM campaigns WHERE id = $campaign");
    $db->exec("DELETE d FROM devices d JOIN user_devices u ON u.device_id = d.id WHERE u.user_id IN ($in)");
    foreach ($cookies as $x) $db->prepare('DELETE FROM devices WHERE cookie_hash = ?')->execute([hash('sha256', $x)]);
    $db->exec("DELETE FROM short_links WHERE account_id IN ($ain)");
    $db->exec("DELETE FROM threads_accounts WHERE id IN ($ain)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
