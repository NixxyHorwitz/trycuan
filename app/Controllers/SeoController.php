<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Campaign;
use App\Models\Setting;
use App\Services\Seo;

final class SeoController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (!Setting::int('seo_index')) {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        echo "User-agent: *\nAllow: /\n";
        foreach (['/admin', '/dashboard', '/campaign', '/utas', '/saldo', '/profil', '/akun-threads', '/penarikan', '/insight', '/materi', '/masuk/google', '/reset-password', '/lupa-password'] as $path) echo "Disallow: $path\n";
        echo "\nSitemap: " . Seo::base() . "/sitemap.xml\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [['/', '1.0'], ['/panduan', '0.8'], ['/daftar', '0.7'], ['/masuk', '0.3'], ['/privasi', '0.2']];
        if (Setting::int('public_campaigns')) {
            $urls[] = ['/program', '0.9'];
            foreach (Campaign::visible(null) as $c) $urls[] = ["/program/{$c['id']}", '0.7'];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if (Setting::int('seo_index')) foreach ($urls as [$path, $priority]) echo '  <url><loc>' . e(Seo::base() . $path) . "</loc><priority>$priority</priority></url>\n";
        echo "</urlset>\n";
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => Setting::str('site_name'), 'short_name' => Setting::str('site_name'), 'description' => Setting::str('seo_description'), 'lang' => 'id', 'start_url' => '/dashboard', 'scope' => '/',
            'display' => 'standalone', 'background_color' => '#f6f6f7', 'theme_color' => '#f6f6f7',
            'icons' => [['src' => '/assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'], ['src' => '/assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
