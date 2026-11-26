<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ClientIp;
use App\Core\View;
use App\Models\Click;
use App\Services\Clicks\Turnstile;

final class ClickController
{
    public function index(): void
    {
        View::render('admin/clicks', [
            'title' => 'Log klik',
            'active' => 'clicks',
            'status' => $status = in_array(query('status'), ['valid', 'held', 'invalid'], true) ? query('status') : '',
            'clicks' => Click::recent(100, $status),
            'layers' => [
                'Tantangan Turnstile' => Turnstile::siteKey() !== '',
                'Negara dan IP asli (Cloudflare)' => ClientIp::behindCloudflare(),
                'Daftar IP hosting' => is_file(ROOT . '/storage/ip/hosting.txt'),
            ],
        ], 'admin');
    }
}
