<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\Setting;

/** Meta, Open Graph, dan data terstruktur untuk halaman publik. */
final class Seo
{
    public const SOCIALS = ['threads' => 'Threads', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram'];

    public static function base(): string
    {
        return rtrim(Env::get('APP_URL'), '/');
    }

    /** Data halaman bawaan dari pengaturan. */
    public static function page(string $key, string $path): array
    {
        return ['title' => Setting::str("seo_{$key}_title"), 'description' => Setting::str("seo_{$key}_description"), 'path' => $path];
    }

    public static function image(): string
    {
        $custom = SiteImage::url(Setting::str('og_image'));
        return self::base() . ($custom ?: '/assets/og.png');
    }

    public static function title(string $title): string
    {
        $suffix = trim(str_replace('{situs}', Setting::str('site_name'), Setting::str('seo_suffix')));
        return $suffix === '' || str_contains($title, $suffix) ? $title : "$title | $suffix";
    }

    /** @return list<string> */
    public static function profiles(): array
    {
        return array_values(array_filter(array_map(static fn (string $key): string => Setting::str("social_$key"), array_keys(self::SOCIALS))));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function faq(): array
    {
        $rows = [];
        foreach (explode("\n", Setting::str('faq')) as $line) {
            [$question, $answer] = array_map('trim', explode('|', $line, 2) + [1 => '']);
            if ($question !== '' && $answer !== '') $rows[] = [$question, $answer];
        }
        return $rows;
    }

    /** Tag untuk <head>. $seo null berarti halaman pribadi yang tidak boleh diindeks. */
    public static function tags(string $title, ?array $seo): string
    {
        $name = Setting::str('site_name');
        $meta = static fn (string $attr, string $key, string $value): string => $value === '' ? '' : '  <meta ' . $attr . '="' . e($key) . '" content="' . e($value) . "\">\n";
        $index = $seo !== null && Setting::int('seo_index') === 1 && empty($seo['noindex']);
        $description = trim($seo['description'] ?? '') ?: Setting::str('seo_description');
        $html = $meta('name', 'description', $description)
            . $meta('name', 'robots', $index ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow');
        if ($seo === null) return $html;

        $url = self::base() . ($seo['path'] === '/' ? '/' : $seo['path']);
        $full = self::title($title);
        $image = self::image();
        $html .= '  <link rel="canonical" href="' . e($url) . "\">\n" . '  <link rel="alternate" hreflang="id" href="' . e($url) . "\">\n"
            . $meta('property', 'og:type', $seo['type'] ?? 'website') . $meta('property', 'og:site_name', $name) . $meta('property', 'og:locale', 'id_ID')
            . $meta('property', 'og:title', $full) . $meta('property', 'og:description', $description) . $meta('property', 'og:url', $url)
            . $meta('property', 'og:image', $image) . $meta('property', 'og:image:alt', $name)
            . ($image === self::base() . '/assets/og.png' ? $meta('property', 'og:image:width', '1200') . $meta('property', 'og:image:height', '630') : '')
            . $meta('property', 'fb:app_id', Setting::str('fb_app_id'))
            . $meta('name', 'twitter:card', 'summary_large_image') . $meta('name', 'twitter:site', Setting::str('x_handle'))
            . $meta('name', 'twitter:title', $full) . $meta('name', 'twitter:description', $description) . $meta('name', 'twitter:image', $image)
            . $meta('name', 'google-site-verification', Setting::str('verify_google')) . $meta('name', 'msvalidate.01', Setting::str('verify_bing'))
            . $meta('name', 'yandex-verification', Setting::str('verify_yandex')) . $meta('name', 'p:domain_verify', Setting::str('verify_pinterest'))
            . $meta('name', 'facebook-domain-verification', Setting::str('verify_facebook'));
        // Blok data, bukan skrip yang dijalankan. JSON_HEX_TAG mencegah penutupan tag dari isi.
        return $html . '  <script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => self::graph($full, $description, $url, $seo)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
    }

    private static function graph(string $title, string $description, string $url, array $seo): array
    {
        $base = self::base();
        $name = Setting::str('site_name');
        $logo = $base . (SiteImage::url(Setting::str('site_logo')) ?: '/assets/icon-512.png');
        $graph = [
            array_filter(['@type' => 'Organization', '@id' => "$base/#org", 'name' => $name, 'url' => "$base/", 'logo' => $logo, 'sameAs' => self::profiles(), 'email' => Setting::str('contact_email')]),
            ['@type' => 'WebSite', '@id' => "$base/#web", 'url' => "$base/", 'name' => $name, 'inLanguage' => 'id-ID', 'publisher' => ['@id' => "$base/#org"]],
            ['@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => $title, 'description' => $description, 'inLanguage' => 'id-ID', 'isPartOf' => ['@id' => "$base/#web"]],
        ];
        if (!empty($seo['crumbs'])) {
            $items = [];
            foreach (['Beranda' => '/'] + $seo['crumbs'] as $label => $path) $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $label, 'item' => $base . $path];
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        if (!empty($seo['faq'])) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $r): array => ['@type' => 'Question', 'name' => $r[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $r[1]]], self::faq())];
        }
        return $graph;
    }
}
