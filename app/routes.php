<?php
declare(strict_types=1);

use App\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Controllers\Admin\ClickController;
use App\Controllers\Admin\OverviewController;
use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CampaignController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\LinkController;
use App\Controllers\ProfileController;
use App\Core\Router;

return static function (Router $r): void {
    $r->add('GET', '/', [HomeController::class, 'index']);
    $r->add('GET', '/privasi', [HomeController::class, 'privacy']);
    $r->add('GET', '/{code}', [LinkController::class, 'show'], 'beacon');
    $r->add('POST', '/{code}/klik', [LinkController::class, 'click'], 'beacon');
    $r->add('GET', '/daftar', [AuthController::class, 'registerForm'], 'guest');
    $r->add('POST', '/daftar', [AuthController::class, 'register'], 'guest');
    $r->add('GET', '/masuk', [AuthController::class, 'loginForm'], 'guest');
    $r->add('POST', '/masuk', [AuthController::class, 'login'], 'guest');
    $r->add('POST', '/keluar', [AuthController::class, 'logout'], 'auth');
    $r->add('GET', '/dashboard', [DashboardController::class, 'index'], 'auth');
    $r->add('GET', '/akun-threads', [AccountController::class, 'index'], 'auth');
    $r->add('POST', '/akun-threads', [AccountController::class, 'start'], 'auth');
    $r->add('GET', '/akun-threads/verifikasi', [AccountController::class, 'index'], 'auth');
    $r->add('POST', '/akun-threads/verifikasi', [AccountController::class, 'verify'], 'auth');
    $r->add('POST', '/akun-threads/batal', [AccountController::class, 'cancel'], 'auth');
    $r->add('GET', '/akun-threads/link', [AccountController::class, 'index'], 'auth');
    $r->add('POST', '/akun-threads/link', [AccountController::class, 'checkLink'], 'auth');
    $r->add('GET', '/campaign', [CampaignController::class, 'index'], 'auth');
    $r->add('GET', '/campaign/{id}', [CampaignController::class, 'detail'], 'auth');
    foreach (['ikut' => 'join', 'batal' => 'leave', 'utas' => 'submit'] as $path => $action) {
        $r->add('GET', "/campaign/{id}/$path", [CampaignController::class, 'detail'], 'auth');
        $r->add('POST', "/campaign/{id}/$path", [CampaignController::class, $action], 'auth');
    }
    $r->add('GET', '/profil', [ProfileController::class, 'index'], 'auth');
    $r->add('POST', '/profil', [ProfileController::class, 'update'], 'auth');
    $r->add('GET', '/profil/password', [ProfileController::class, 'index'], 'auth');
    $r->add('POST', '/profil/password', [ProfileController::class, 'password'], 'auth');
    $r->add('GET', '/admin', [OverviewController::class, 'index'], 'admin');
    $r->add('GET', '/admin/klik', [ClickController::class, 'index'], 'admin');
    $r->add('GET', '/admin/campaign', [AdminCampaignController::class, 'index'], 'admin');
    $r->add('GET', '/admin/campaign/baru', [AdminCampaignController::class, 'createForm'], 'admin');
    $r->add('POST', '/admin/campaign/baru', [AdminCampaignController::class, 'create'], 'admin');
    $r->add('GET', '/admin/campaign/{id}', [AdminCampaignController::class, 'editForm'], 'admin');
    $r->add('POST', '/admin/campaign/{id}', [AdminCampaignController::class, 'update'], 'admin');
};
