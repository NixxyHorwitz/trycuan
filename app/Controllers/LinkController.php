<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\View;
use App\Models\Device;
use App\Models\ShortLink;
use App\Services\Clicks\ClickRequest;
use App\Services\Clicks\ClickTracker;
use App\Services\Clicks\Token;
use App\Services\Clicks\Turnstile;

/** Halaman publik tanpa sesi login. */
final class LinkController
{
    public function show(string $code): void
    {
        $link = ShortLink::resolve($code, ctype_digit(query('c')) ? (int) query('c') : null) ?? throw new HttpException(404, 'Link tidak ditemukan.');
        header('X-Robots-Tag: noindex, nofollow');
        $campaign = $link['campaign'];
        $siteKey = $campaign ? Turnstile::siteKey() : '';
        if ($siteKey !== '') {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        }
        View::render('link', [
            'title' => $campaign['title'] ?? ($link['campaigns'] ? 'Pilih tujuan' : 'Link belum aktif'),
            'campaign' => $campaign,
            'campaigns' => $campaign ? [] : $link['campaigns'],
            'code' => $code,
            'siteKey' => $siteKey,
            'token' => $campaign ? Token::make($code, (int) $campaign['id'], Device::issue()) : '',
        ], 'link');
    }

    public function click(string $code): void
    {
        http_response_code(204);
        if (!RateLimiter::hit('click:' . ClientIp::current(), 120, 60)) return;
        $body = json_decode((string) file_get_contents('php://input', length: 8192), true);
        if (is_array($body)) (new ClickTracker())->record($code, ClickRequest::fromHttp($body));
    }
}
