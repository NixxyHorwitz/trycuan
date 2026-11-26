<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Models\Audit;
use App\Models\Report;
use App\Models\Submission;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\FeeCredit;
use App\Services\Threads\ScrapeGuard;

final class OverviewController
{
    public function index(): void
    {
        $fees = FeeCredit::queue();
        $days = in_array(query('hari'), ['7', '14', '30'], true) ? (int) query('hari') : 14;
        View::render('admin/overview', [
            'title' => 'Ringkasan',
            'days' => $days,
            'chart' => Report::daily($days),
            'totals' => Report::totals(),
            'top' => Report::topAccounts(),
            'logs' => Audit::page(PHP_INT_MAX, '', 6),
            'active' => 'overview',
            'counts' => User::counts(),
            'pending' => Submission::pendingCount(),
            'withdrawals' => Withdrawal::pendingCount(),
            'fees' => count(array_filter($fees, static fn (array $r): bool => $r['ready'] > 0)),
            'scraper' => ScrapeGuard::health(),
        ], 'admin');
    }
}
