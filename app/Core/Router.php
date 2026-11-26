<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Device;

final class Router
{
    private array $routes = [];

    /** @param 'public'|'guest'|'auth'|'admin'|'beacon' $access */
    public function add(string $method, string $path, array $handler, string $access = 'public'): void
    {
        $this->routes[$path][$method] = [$handler, $access];
    }

    /** Kode shortlink selalu memuat huruf besar dan angka, jadi tidak bentrok dengan route lain. */
    public static function isShortLink(string $uri): bool
    {
        return preg_match('~^/(?=[^/]*[A-Z])(?=[^/]*\d)[A-Za-z0-9]{7}(/klik)?/?(\?|$)~', $uri) === 1;
    }

    /** @return array<string, array<string, array{0: array, 1: string}>> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(explode('?', $uri, 2)[0], '/');
        if ($method === 'HEAD') $method = 'GET';

        // Satu angka di path boleh cocok dengan {id}.
        $page = $path;
        $args = [];
        if (self::isShortLink($uri)) {
            $args = [substr($path, 1, 7)];
            $path = '/{code}' . substr($path, 8);
        }
        if (!isset($this->routes[$path]) && preg_match('~^(/[^0-9]*/)([1-9]\d{0,17})(/[^0-9]*)?$~D', $path, $m)) {
            $path = $m[1] . '{id}' . ($m[3] ?? '');
            $args = [(int) $m[2]];
        }
        $methods = $this->routes[$path] ?? throw new HttpException(404, 'Halaman tidak ditemukan.');
        if (!isset($methods[$method])) {
            header('Allow: ' . implode(', ', array_keys($methods)));
            throw new HttpException(405, 'Metode tidak diizinkan.');
        }
        [$handler, $access] = $methods[$method];
        // Beacon klik dilindungi token klik, bukan CSRF.
        if ($method === 'POST' && $access !== 'beacon') Csrf::verify();

        $user = $access === 'beacon' ? null : Auth::user();
        if ($access === 'guest' && $user) redirect(Area::home());
        if (in_array($access, ['auth', 'admin'], true) && !$user) {
            // Jalur sudah dinormalkan, jadi tidak bisa mengarah ke situs lain.
            if ($method === 'GET' && !preg_match('~/(ekspor|bukti|cari|materi/\d+)$~', $page)) $_SESSION['to'] = $page;
            redirect(Area::login());
        }
        if ($access === 'admin' && $user['role'] !== 'admin') throw new HttpException(403, 'Halaman ini khusus admin.');

        // Dipakai filter klik untuk mengenali perangkat pemilik akun.
        if ($user && ($_SESSION['device_day'] ?? '') !== date('Y-m-d')) {
            Device::linkUser((int) $user['id']);
            $_SESSION['device_day'] = date('Y-m-d');
        }

        [$class, $action] = $handler;
        (new $class())->$action(...$args);
    }
}
