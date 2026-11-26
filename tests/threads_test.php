<?php
// Tes scraper tanpa internet: php tests/threads_test.php
// Halaman tiruan meniru struktur JSON Threads per 25 Nov 2026, dengan data fiktif.
declare(strict_types=1);

use App\Core\Database;
use App\Services\Threads\CurlFetcher;
use App\Services\Threads\Fetcher;
use App\Services\Threads\Identifier;
use App\Services\Threads\Outcome;
use App\Services\Threads\Page;
use App\Services\Threads\PageParser;
use App\Services\Threads\ScrapeGuard;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};
$fails = static function (callable $run): ?Outcome {
    try {
        $run();
    } catch (ThreadsException $error) {
        return $error->outcome;
    }
    return null;
};

const CODE = 'DdCYWl7GktV';
const POST_URL = 'https://www.threads.com/@tes_akun/post/' . CODE;
const PROFILE_URL = 'https://www.threads.com/@tes_akun';

function html(array ...$docs): string
{
    $out = '<!doctype html><html><head><title>x</title></head><body>';
    foreach ($docs as $doc) $out .= '<script type="application/json" data-sjs>' . json_encode($doc) . '</script>';
    return $out . '</body></html>';
}
function media(array $over = []): array
{
    return ['result' => ['data' => ['media' => array_replace([
        'code' => CODE, 'user' => ['pk' => '111', 'username' => 'tes_akun'], 'taken_at' => 1790000000, 'like_count' => 12,
        'caption' => ['text' => 'Halo </script> "view_counts":999999'], 'text_post_app_info' => ['is_reply' => false],
    ], $over)]]];
}
function route(?string $id = null, string $author = '111'): array
{
    return ['rootView' => ['props' => ['post_id' => $id ?? Identifier::postId(CODE), 'post_author_id' => $author]]];
}
function views(mixed $value): array
{
    return ['define' => [['Config', [], ['enable_view_counts' => false, 'view_counts' => $value], 7623]]];
}
function related(): array
{
    return ['relatedPosts' => ['post' => ['code' => 'De61OdZGvdi', 'user' => ['pk' => '222', 'username' => 'orang_lain'], 'taken_at' => 1, 'like_count' => 9]]];
}
function profileDoc(array $over = []): array
{
    return ['data' => ['user' => array_replace(['pk' => '111', 'username' => 'tes_akun', 'biography' => 'Kode TC-7K4M9Q2X', 'bio_links' => [['url' => 'https://trycuan.online/AbCdEfG']], 'text_post_app_is_private' => false], $over)]];
}
function profileRoute(string $id = '111'): array
{
    return ['props' => ['user_id' => $id, 'is_self_profile' => false]];
}

$p = new PageParser();
$post = static fn (string $html, string $url = POST_URL, int $status = 200) => $p->post(new Page($status, $url, $html), CODE);
$profile = static fn (string $html, string $url = PROFILE_URL, int $status = 200) => $p->profile(new Page($status, $url, $html), 'tes_akun');

// URL dan ID
$check('Kode ke ID utas cocok dengan Threads', Identifier::postId(CODE) === '3981852126213720917');
$check('URL biasa, net, dan query diterima', Identifier::codeFromUrl(POST_URL) === CODE
    && Identifier::codeFromUrl('https://threads.net/@a.b/post/' . CODE . '/?x=1') === CODE);
foreach (['http://www.threads.com/@a/post/' . CODE, 'https://threads.com.evil.test/@a/post/' . CODE, 'https://evil.test/https://www.threads.com/@a/post/' . CODE,
    'https://www.threads.com@evil.test/@a/post/' . CODE, 'https://www.threads.com/@a/post/../../x', 'https://www.threads.com/@a', 'https://www.threads.com/@a/post/___________'] as $bad) {
    $check("URL ditolak: $bad", Identifier::codeFromUrl($bad) === null);
}
$check('Username dirapikan dan divalidasi', Identifier::username(' @Tes.Akun ') === 'tes.akun' && Identifier::username('a/../b') === null && Identifier::username('') === null);
$check('Redirect hanya ke host Threads', CurlFetcher::allowed('https://www.threads.com/x') && !CurlFetcher::allowed('http://www.threads.com/')
    && !CurlFetcher::allowed('https://127.0.0.1/') && !CurlFetcher::allowed('https://www.threads.com@evil.test/') && !CurlFetcher::allowed('https://www.threads.com:8443/'));

// Utas
$good = $post(html(related(), views(675037), route(), media()));
$check('Utas normal terbaca', $good->views === 675037 && $good->ownerPk === '111' && $good->ownerUsername === 'tes_akun' && $good->likes === 12 && !$good->isReply);
$check('Teks utas berisi "view_counts" tidak mengecoh', $good->views === 675037 && str_contains($good->text, '999999'));
$check('Views 0 tetap 0', $post(html(views(0), route(), media()))->views === 0);
$check('Views null tetap null', $post(html(views(null), route(), media()))->views === null);
$check('Balasan ditandai', $post(html(views(1), route(), media(['text_post_app_info' => ['is_reply' => true]])))->isReply);
$check('Pemilik dari data, bukan dari URL', $post(html(views(5), route(null, '333'), media(['user' => ['pk' => '333', 'username' => 'asli']])), 'https://www.threads.com/@palsu/post/' . CODE)->ownerPk === '333');
$check('Utas hilang', $fails(fn () => $post(html(views(null)), 'https://www.threads.com/?error=invalid_post')) === Outcome::NotFound);
$check('Dialihkan ke utas lain', $fails(fn () => $post(html(views(9), route(), media()), 'https://www.threads.com/@tes_akun/post/De61OdZGvdi')) === Outcome::NotFound);
$check('Login wall', $fails(fn () => $post('', 'https://www.threads.com/login/?next=x')) === Outcome::LoginRequired);
$check('HTTP 429 dan 403', $fails(fn () => $post('', POST_URL, 429)) === Outcome::Blocked && $fails(fn () => $post('', POST_URL, 403)) === Outcome::Blocked);
$check('HTTP 500', $fails(fn () => $post('', POST_URL, 500)) === Outcome::Network);
$check('ID halaman beda ditolak', $fails(fn () => $post(html(views(9), route('123'), media()))) === Outcome::ParseError);
$check('Pemilik halaman beda ditolak', $fails(fn () => $post(html(views(9), route(null, '999'), media()))) === Outcome::ParseError);
$check('Hanya utas rekomendasi ditolak', $fails(fn () => $post(html(views(9), route(), related()))) === Outcome::ParseError);
$check('Field views hilang menjadi null', $post(html(route(), media()))->views === null && $post(html(media()))->views === null);
$check('Views ganda berbeda ditolak', $fails(fn () => $post(html(views(1), views(2), route(), media()))) === Outcome::ParseError);
$check('Views teks atau negatif ditolak', $fails(fn () => $post(html(views('675K'), route(), media()))) === Outcome::ParseError && $fails(fn () => $post(html(views(-1), route(), media()))) === Outcome::ParseError);
$check('HTML rusak ditolak', $fails(fn () => $post('<script type="application/json">{rusak')) === Outcome::ParseError);

// Profil
$u = $profile(html(profileRoute(), profileDoc()));
$check('Profil, bio, dan link terbaca', $u->pk === '111' && str_contains($u->biography, 'TC-7K4M9Q2X') && $u->links === ['https://trycuan.online/AbCdEfG'] && !$u->isPrivate);
$check('Tanpa bio dan link', ($e = $profile(html(profileRoute(), profileDoc(['biography' => null, 'bio_links' => []]))))->biography === '' && $e->links === []);
$check('Akun privat ditandai', $profile(html(profileRoute(), profileDoc(['text_post_app_is_private' => true])))->isPrivate);
$check('Profil orang lain ditolak', $fails(fn () => $profile(html(profileRoute(), profileDoc(['username' => 'orang_lain'])))) === Outcome::ParseError);
$check('ID profil beda ditolak', $fails(fn () => $profile(html(profileRoute('999'), profileDoc()))) === Outcome::ParseError);
$check('Akun tidak ada', $fails(fn () => $profile('', 'https://www.threads.com/login/?next=x')) === Outcome::LoginRequired);

// Circuit breaker, dengan transaksi yang dibatalkan supaya data lokal tidak berubah
final class FakeFetcher implements Fetcher
{
    public int $calls = 0;
    public function __construct(public Page $page)
    {
    }
    public function get(string $path): Page
    {
        $this->calls++;
        return $this->page;
    }
}
$db = Database::pdo();
$db->beginTransaction();
try {
    $db->exec('DELETE FROM rate_limits');
    $db->exec('UPDATE scrape_state SET failures = 0, open_until = 0 WHERE id = 1');
    $ok = new Page(200, POST_URL, html(views(9), route(), media()));
    $f = new FakeFetcher(new Page(500, POST_URL, ''));
    $s = new ThreadsScraper($f);
    for ($i = 0; $i < ScrapeGuard::THRESHOLD - 1; $i++) $fails(fn () => $s->post(CODE));
    $f->page = $ok;
    $check('Sukses sebelum ambang', $s->post(CODE)->views === 9);
    $f->page = new Page(500, POST_URL, '');
    for ($i = 0; $i < ScrapeGuard::THRESHOLD; $i++) $fails(fn () => $s->post(CODE));
    $calls = $f->calls;
    $check('Gagal beruntun menjeda tanpa request baru', $fails(fn () => $s->post(CODE)) === Outcome::Paused && $f->calls === $calls);
    $db->exec('UPDATE scrape_state SET open_until = 0 WHERE id = 1');
    $fails(fn () => $s->post(CODE));
    $check('Gagal setelah jeda langsung menjeda lagi', $fails(fn () => $s->post(CODE)) === Outcome::Paused);
    $db->exec('UPDATE scrape_state SET open_until = 0 WHERE id = 1');
    $f->page = $ok;
    $s->post(CODE);
    $check('Pulih setelah sukses', (int) $db->query('SELECT failures FROM scrape_state')->fetchColumn() === 0);
    $f->page = new Page(200, 'https://www.threads.com/?error=invalid_post', '');
    for ($i = 0; $i < ScrapeGuard::THRESHOLD + 2; $i++) $last = $fails(fn () => $s->post(CODE));
    $check('Utas hilang tidak menjeda sistem', $last === Outcome::NotFound);
    $f->page = new Page(429, POST_URL, '');
    $fails(fn () => $s->post(CODE));
    $check('HTTP 429 langsung menjeda', $fails(fn () => $s->post(CODE)) === Outcome::Paused);
    $db->exec('UPDATE scrape_state SET failures = 0, open_until = 0 WHERE id = 1');
    $f->page = $ok;
    $last = null;
    for ($i = 0; $i < 45; $i++) $last = $fails(fn () => $s->post(CODE));
    $check('Batas laju global berlaku', $last === Outcome::Paused);
    $check('Log tercatat', (int) $db->query("SELECT COUNT(*) FROM scrape_logs WHERE target = 'post:" . CODE . "' AND outcome = 'blocked'")->fetchColumn() === 1);
} finally {
    $db->rollBack();
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
