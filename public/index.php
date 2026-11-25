<?php
declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

require dirname(__DIR__) . '/app/bootstrap.php';

header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

try {
    // Shortlink publik tidak memakai sesi login.
    if (!Router::isShortLink($_SERVER['REQUEST_URI'])) Session::start();
    $router = new Router();
    (require ROOT . '/app/routes.php')($router);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (HttpException $error) {
    View::render('error', ['title' => $error->getMessage(), 'code' => $error->status, 'message' => $error->getMessage()], 'public', $error->status);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(500);
    // Tanpa layout: layout butuh database, yang mungkin justru sedang gagal.
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Gangguan server</title><p>Terjadi gangguan di server. Coba lagi beberapa saat.</p></html>';
}
