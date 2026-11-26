<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Announcement;
use App\Models\Ledger;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Services\AccountCard;

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $account = ThreadsAccount::forUser((int) $user['id']);
        $running = $account ? Participation::running((int) $account['id']) : [];
        $campaign = $running[0] ?? null;
        foreach ($running as $row) if ($row['id'] == query('campaign')) $campaign = $row;
        $part = $campaign ? Participation::find((int) $account['id'], (int) $campaign['id']) : null;
        $link = $account ? ShortLink::forAccount((int) $account['id']) : null;
        View::render('dashboard', [
            'title' => 'Beranda',
            'balance' => Ledger::balance((int) $user['id']),
            'notice' => Announcement::unread($user) ? (Announcement::published(1)[0] ?? null) : null,
            'active' => 'dashboard',
            'account' => $account,
            'campaign' => $campaign,
            'linked' => $link && $link['link_verified_at'] !== null,
            'card' => $account ? AccountCard::build($account, (int) ($campaign['id'] ?? 0)) : null,
            'running' => $running,
            'posts' => $part ? count(Submission::forParticipation((int) $part['id'])) : 0,
        ], 'app');
    }
}
