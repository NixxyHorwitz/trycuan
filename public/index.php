<?php
declare(strict_types=1);

use App\Core\Area;
use App\Core\Auth;
use App\Core\Http;
use App\Core\HttpException;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;
use App\Models\Visit;

require dirname(__DIR__) . '/app/bootstrap.php';

header_remove('X-Powered-By');
// Hash: gaya bilah progres yang disuntik Turbo.
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'sha256-WAyOw4V+FqDc35lQPyRADLBWbuNK8ahvYEaQIYF1+Ps='; img-src 'self' data: https://api.dicebear.com; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
Http::enforce();

try {
    // Shortlink publik tidak memakai sesi login.
    if (!Router::isShortLink($_SERVER['REQUEST_URI'])) Session::start();
    $link = Router::isShortLink($_SERVER['REQUEST_URI']);
    if (!$link && !Area::isAdmin() && Setting::int('maintenance') && !preg_match('~^/(robots\.txt|sitemap\.xml)$~', $_SERVER['REQUEST_URI'])) throw new HttpException(503, Setting::str('maintenance_text'));
    $router = new Router();
    (require ROOT . '/app/routes.php')($router);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    $html = !array_filter(headers_list(), static fn (string $h): bool => stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false);
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$link && !Area::isAdmin() && $html && http_response_code() === 200) {
        try {
            Visit::record(Auth::user() !== null);
        } catch (PDOException $error) {
            // Halaman sudah terkirim; catatan kunjungan boleh gagal.
            error_log((string) $error);
        }
    }
} catch (HttpException $error) {
    View::render('error', ['title' => $error->getMessage(), 'code' => $error->status, 'message' => $error->getMessage()], 'public', $error->status);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(500);
    // Tanpa layout: layout butuh database, yang mungkin justru sedang gagal.
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Gangguan server</title><p>Terjadi gangguan di server. Coba lagi beberapa saat.</p></html>';
}
