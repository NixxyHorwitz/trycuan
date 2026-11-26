<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\View;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\Setting;
use App\Services\Markdown;
use App\Services\Seo;

final class HomeController
{
    public function index(): void
    {
        $open = Setting::int('public_campaigns') ? array_slice(Campaign::visible(null), 0, 3) : [];
        View::render('home', ['title' => Setting::str('seo_home_title'), 'seo' => Seo::page('home', '/') + ['faq' => Seo::faq() !== []], 'campaigns' => $open, 'faq' => Seo::faq()]);
    }

    public function guide(): void
    {
        $markdown = new Markdown('p-');
        $html = $markdown->render(Setting::str('guide_text'));
        View::render('guide', ['title' => Setting::str('seo_panduan_title'), 'seo' => Seo::page('panduan', '/panduan') + ['type' => 'article', 'crumbs' => ['Panduan' => '/panduan']], 'html' => $html, 'toc' => $markdown->toc]);
    }

    public function programs(): void
    {
        $this->ensurePublic();
        View::render('programs', ['title' => Setting::str('seo_program_title'), 'seo' => Seo::page('program', '/program') + ['crumbs' => ['Campaign' => '/program']], 'campaigns' => Campaign::visible(null)]);
    }

    public function program(int $id): void
    {
        $this->ensurePublic();
        $campaign = null;
        foreach (Campaign::visible(null) as $row) if ((int) $row['id'] === $id) $campaign = $row;
        if (!$campaign) throw new HttpException(404, 'Campaign tidak ditemukan atau sudah ditutup.');
        $until = date('d/m/Y', strtotime($campaign['ends_at']));
        View::render('program', ['title' => $campaign['title'], 'campaign' => $campaign, 'seo' => [
            'path' => "/program/$id", 'crumbs' => ['Campaign' => '/program', $campaign['title'] => "/program/$id"],
            'description' => "Campaign {$campaign['title']}: fee " . rupiah((int) $campaign['rate_per_1k']) . ' per 1.000 views, maksimum ' . rupiah((int) $campaign['cap_per_account']) . " per akun, sampai $until.",
        ]]);
    }

    public function announcements(): void
    {
        $user = Auth::user();
        // Dibaca sebelum ditandai, supaya label Baru masih tampil sekali.
        $rows = Announcement::published();
        Announcement::markSeen((int) $user['id']);
        View::render('announcements', ['title' => 'Pengumuman', 'active' => 'notice', 'rows' => $rows, 'seen' => (int) $user['seen_announcement']], 'app');
    }

    public function privacy(): void
    {
        View::render('privacy', ['title' => 'Privasi', 'seo' => ['path' => '/privasi', 'description' => 'Data yang dicatat, kegunaannya, dan berapa lama disimpan.']]);
    }

    private function ensurePublic(): void
    {
        if (!Setting::int('public_campaigns')) throw new HttpException(404, 'Halaman tidak ditemukan.');
    }
}
