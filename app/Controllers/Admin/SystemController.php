<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Services\Csv;
use App\Services\Threads\ScrapeGuard;

/** Kesehatan scraper, audit, dan pengaturan. */
final class SystemController
{
    public function scraper(): void
    {
        View::render('admin/scraper', ['title' => 'Scraper', 'active' => 'scraper', 'health' => ScrapeGuard::health(), 'logs' => ScrapeGuard::recent()], 'admin');
    }

    public function resume(): void
    {
        ScrapeGuard::resume();
        Audit::add((int) Auth::user()['id'], 'scraper.resumed', 'scraper');
        Session::flash('Jeda scraper dibuka.');
        redirect('/admin/scraper');
    }

    public function audit(): void
    {
        $before = $_GET['sebelum'] ?? '';
        $before = is_string($before) && preg_match('/^\d{1,18}$/D', $before) ? (int) $before : PHP_INT_MAX;
        $group = preg_match('/^[a-z_]{1,30}$/D', query('jenis')) ? query('jenis') : '';
        View::render('admin/audit', ['title' => 'Audit', 'active' => 'audit', 'rows' => Audit::page($before, $group), 'group' => $group, 'groups' => Audit::groups()], 'admin');
    }

    public function exportAudit(): void
    {
        $rows = Audit::page(PHP_INT_MAX, '', 5000);
        Audit::add((int) Auth::user()['id'], 'audit.exported', 'audit', ['rows' => count($rows)]);
        Csv::send('audit', ['ID', 'Pelaku', 'Tindakan', 'Sasaran', 'Detail', 'Waktu'], array_map(static fn (array $r): array =>
            [$r['id'], $r['name'] ?? 'Sistem', $r['action'], $r['target'], $r['meta_json'], $r['created_at']], $rows));
    }
}
