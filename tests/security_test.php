<?php
// Tes reset password, SMTP, dan akses semua route: php tests/security_test.php [base_url]
// Memakai data sementara; dihapus di akhir.
declare(strict_types=1);

use App\Core\Database;
use App\Core\Http;
use App\Core\RateLimiter;
use App\Core\Router;
use App\Models\User;
use App\Services\Mail\Mailer;
use App\Services\Mail\SmtpMailer;
use App\Services\PasswordReset;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/Client.php';

// Server SMTP tiruan
if (($argv[1] ?? '') === '--smtp') {
    $server = stream_socket_server('tcp://127.0.0.1:0');
    echo parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT) . "\n";
    $c = stream_socket_accept($server, 10);
    fwrite($c, "220 siap\r\n");
    $data = false;
    $steps = [];
    $login = 0;
    while (($line = fgets($c)) !== false) {
        $steps[] = rtrim($line);
        if ($data) {
            if ($line !== ".\r\n") continue;
            $data = false;
            fwrite($c, "250 diterima\r\n");
        } elseif (str_starts_with($line, 'EHLO')) fwrite($c, "250-uji\r\n250 AUTH LOGIN\r\n");
        elseif (str_starts_with($line, 'AUTH')) {
            $login = 2;
            fwrite($c, "334 VXNlcg==\r\n");
        } elseif ($login) {
            $login--;
            fwrite($c, $login ? "334 UGFzcw==\r\n" : (trim($line) === base64_encode('rahasia') ? "235 ok\r\n" : "535 salah\r\n"));
        } elseif (str_starts_with($line, 'DATA')) {
            $data = true;
            fwrite($c, "354 mulai\r\n");
        } elseif (str_starts_with($line, 'QUIT')) break;
        else fwrite($c, "250 ok\r\n");
    }
    echo json_encode($steps);
    exit;
}

final class FakeMailer extends Mailer
{
    public array $sent = [];

    public function send(string $to, string $subject, string $text): void
    {
        $this->sent[] = [$to, $subject, $text];
    }
}

$base = local_base($argv[1] ?? 'http://trycuan.test');
$failed = 0;
$check = static function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'LULUS ' : 'GAGAL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};

// SMTP
$smtp = static function (string $pass, string $to = 'tujuan@example.test') use (&$steps): string {
    $server = popen('"' . PHP_BINARY . '" "' . __FILE__ . '" --smtp', 'r');
    $port = (int) fgets($server);
    $error = '';
    try {
        (new SmtpMailer('127.0.0.1', $port, 'none', 'akun', $pass, 'dari@example.test'))->send($to, "Judul é\r\nBcc: x@y.z", "Baris satu\r\n.\r\nBaris tiga\n");
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        // Bebaskan server tiruan bila tidak ada koneksi.
        if ($s = @stream_socket_client("tcp://127.0.0.1:$port", $x, $y, 1)) fclose($s);
    }
    $steps = json_decode((string) stream_get_contents($server), true) ?? [];
    pclose($server);
    return $error;
};
$check('SMTP mengirim', $smtp('rahasia') === '' && in_array('RCPT TO:<tujuan@example.test>', $steps, true));
$blank = array_search('', $steps, true);
$check('Isi utuh walau ada baris titik', base64_decode(implode('', array_slice($steps, $blank + 1, array_search('.', $steps, true) - $blank - 1))) === "Baris satu\r\n.\r\nBaris tiga\n");
$check('Judul tidak bisa menyisipkan header', !array_filter($steps, static fn (string $l): bool => str_starts_with($l, 'Bcc')));
$check('SMTP password salah gagal tanpa membocorkan', str_contains($error = $smtp('salahpass'), '535') && !str_contains($error, 'salahpass'));
$check('Alamat dengan baris baru ditolak', $smtp('rahasia', "a@example.test\r\nRCPT TO:<b@c.d>") !== '');
try {
    (new SmtpMailer('smtp.example.test', 25, 'none', '', '', 'a@example.test'))->send('b@example.test', 'x', 'y');
    $check('SMTP tanpa enkripsi ke luar ditolak', false);
} catch (RuntimeException $e) {
    $check('SMTP tanpa enkripsi ke luar ditolak', str_contains($e->getMessage(), 'SMTP_SECURE'));
}

// HTTPS
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$check('X-Forwarded-Proto palsu diabaikan', !Http::secure());
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$_SERVER['HTTPS'] = 'on';
$check('HTTPS terdeteksi', Http::secure());
unset($_SERVER['HTTPS']);

$db = Database::pdo();
$run = bin2hex(random_bytes(5));
$password = 'Rahasia-' . $run;
$emails = ["sec-a-$run@example.test", "sec-b-$run@example.test"];
$ids = array_map(static fn (string $e): int => User::create('Tes', $e, $password), $emails);
$log = ROOT . '/storage/logs/mail.log';
$logBefore = is_file($log) ? file_get_contents($log) : null;
$clear = static function () use ($emails): void {
    foreach (['127.0.0.1', '::1'] as $ip) {
        foreach (['login-ip', 'reset-ip', 'reset-try'] as $k) RateLimiter::clear("$k:$ip");
        foreach ($emails as $x) RateLimiter::clear("login:$ip:$x");
    }
    foreach ([...$emails, 'tidak-ada@example.test'] as $x) RateLimiter::clear("reset-mail:$x");
};
$clear();

try {
    // Reset password
    $mail = new FakeMailer();
    $reset = new PasswordReset($mail);
    $reset->request('tidak-ada@example.test');
    $check('Email tidak terdaftar tidak dikirimi', $mail->sent === []);
    $token = static function () use ($mail): string {
        preg_match('/token=([0-9a-f]{64})/', end($mail->sent)[2], $m);
        return $m[1];
    };
    $reset->request($emails[0]);
    $first = $token();
    $check('Token disimpan sebagai hash', $db->query("SELECT token_hash FROM password_resets WHERE user_id = {$ids[0]}")->fetchColumn() === hash('sha256', $first));
    $check('Link memakai APP_URL', str_contains(end($mail->sent)[2], rtrim(App\Core\Env::get('APP_URL'), '/') . '/reset-password?token='));
    $reset->request($emails[0]);
    $second = $token();
    $check('Permintaan baru mematikan link lama', PasswordReset::userFor($first) === null && PasswordReset::userFor($second) !== null);
    $db->exec("UPDATE password_resets SET expires_at = " . (time() - 1) . " WHERE user_id = {$ids[0]}");
    $check('Token kedaluwarsa ditolak', !PasswordReset::complete($second, 'Baru-12345'));
    $reset->request($emails[0]);
    $third = $token();
    $db->exec("UPDATE users SET status = 'blocked' WHERE id = {$ids[0]}");
    $count = count($mail->sent);
    $reset->request($emails[0]);
    $check('User diblokir tidak bisa reset', !PasswordReset::complete($third, 'Baru-12345') && count($mail->sent) === $count);
    $db->exec("UPDATE users SET status = 'active' WHERE id = {$ids[0]}");
    $check('Token rusak ditolak', PasswordReset::userFor('') === null && PasswordReset::userFor(str_repeat('g', 64)) === null);

    $c = new Client($base);
    $c->post('/masuk', ['email' => $emails[0], 'password' => $password], '/masuk');
    $w = new Client($base);
    $check('Token dipindah dari URL', $w->get("/reset-password?token=$third")->status === 303 && !str_contains($w->headers, $third) && stripos($w->headers, 'no-referrer') !== false);
    $check('Form reset tampil', $w->get('/reset-password')->status === 200 && !str_contains($w->body, $third));
    $check('Password pendek ditolak', $w->post('/reset-password', ['password' => 'abc'], '/reset-password')->status === 422);
    $check('Ulangi password beda ditolak', $w->post('/reset-password', ['password' => 'Baru-74193', 'password_again' => 'Baru-74194'], '/reset-password')->status === 422);
    $check('Tanpa CSRF ditolak', $w->post('/reset-password', ['password' => 'Baru-12345'])->status === 403);
    $check('Reset berhasil', $w->post('/reset-password', ['password' => 'Baru-74193', 'password_again' => 'Baru-74193'], '/reset-password')->status === 303 && password_verify('Baru-74193', User::find($ids[0])['password_hash']));
    $check('Sesi lama dikeluarkan', $c->get('/dashboard')->status === 303);
    $check('Token hanya sekali pakai', !PasswordReset::complete($third, 'Lain-12345') && $w->get("/reset-password?token=$third")->get('/reset-password')->status === 410);

    $known = (new Client($base))->post('/lupa-password', ['email' => $emails[1]], '/lupa-password');
    $unknown = (new Client($base))->post('/lupa-password', ['email' => 'tidak-ada@example.test'], '/lupa-password');
    $check('Respons sama untuk email terdaftar dan tidak', $known->status === 303 && $unknown->status === 303 && $known->body === $unknown->body);
    for ($i = 0; $i < 20 && !$db->query("SELECT 1 FROM password_resets WHERE user_id = {$ids[1]}")->fetchColumn(); $i++) usleep(100000);
    usleep(200000);
    $check('Email lokal tertulis di log', str_contains((string) file_get_contents($log), $emails[1]));
    for ($i = 0; $i < 12; $i++) $known->post('/lupa-password', ['email' => 'bukan@example.test'], '/lupa-password');
    $check('Permintaan reset dibatasi', $known->status === 429);
    RateLimiter::clear('reset-mail:bukan@example.test');
    $clear();

    // Semua route
    $router = new Router();
    (require ROOT . '/app/routes.php')($router);
    $guest = new Client($base);
    $free = new Client($base);
    $free->post('/masuk', ['email' => $emails[1], 'password' => $password], '/masuk');
    $problems = [];
    $total = 0;
    foreach ($router->routes() as $path => $methods) {
        foreach ($methods as $method => [, $access]) {
            $total++;
            $url = str_replace(['{id}', '{code}'], ['999999999', 'Zz9Zz9Z'], $path);
            $open = static fn (Client $x): Client => $method === 'POST' ? $x->post($url, []) : $x->get($url);
            if ($method === 'POST' && $access !== 'beacon') {
                if ($open($guest)->status !== 403 || $open($free)->status !== 403) $problems[] = "CSRF $path";
                // Dengan CSRF sah, hak akses tetap diperiksa.
                $csrf = $guest->get(str_starts_with($path, '/admin') ? '/admin/masuk' : '/masuk')->csrf();
                if (in_array($access, ['auth', 'admin'], true) && $guest->post($url, ['_csrf' => $csrf])->status !== 303) $problems[] = "tamu POST $path";
                if ($access === 'admin' && $free->post($url, ['_csrf' => $free->get('/profil')->csrf()])->status !== 403) $problems[] = "freelancer POST $path";
            }
            if ($method === 'GET' && in_array($access, ['auth', 'admin'], true) && ($open($guest)->status !== 303 || !str_contains($guest->headers, '/masuk'))) $problems[] = "tamu GET $path";
            if ($method === 'GET' && $access === 'admin' && ($open($free)->status !== 303 || !str_contains($free->headers, '/admin/masuk'))) $problems[] = "freelancer GET $path";
            if ($method === 'GET' && $open($guest)->status >= 500) $problems[] = "500 tamu $path";
            if ($method === 'GET' && $open($free)->status >= 500) $problems[] = "500 freelancer $path";
        }
    }
    echo $problems ? '  ' . implode("\n  ", $problems) . "\n" : '';
    $check("Hak akses $total route", $problems === []);
    $h = $guest->get('/masuk')->headers;
    foreach (['Content-Security-Policy', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy', 'no-store'] as $name) $check("Header $name", stripos($h, $name) !== false);
    $check('Versi PHP tidak dibocorkan', stripos($h, 'X-Powered-By') === false);
    foreach (['/.env', '/.git/config', '/DEPLOY.md', '/storage/logs/mail.log', '/storage/backups/', '/storage/proofs/', '/bin/backup.php', '/app/routes.php', '/tests/security_test.php', '/public/../.env'] as $path) {
        $check("Tertutup $path", in_array($guest->get($path)->status, [400, 403, 404], true));
    }
} finally {
    $clear();
    // Hapus hanya baris tes dari log email lokal.
    if ($logBefore === null) @unlink($log);
    else file_put_contents($log, $logBefore);
    $in = implode(',', $ids);
    $db->exec("DELETE FROM audit_logs WHERE actor_id IN ($in)");
    $db->exec("DELETE d FROM devices d JOIN user_devices u ON u.device_id = d.id WHERE u.user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
echo $failed ? "\n$failed tes gagal.\n" : "\nSemua tes lulus.\n";
exit($failed ? 1 : 0);
