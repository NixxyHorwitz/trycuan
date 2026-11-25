<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Click;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;
use App\Models\ThreadsAccount;

final class DashboardController
{
    public function index(): void
    {
        $account = ThreadsAccount::forUser((int) Auth::user()['id']);
        $campaign = $account ? Participation::current((int) $account['id']) : null;
        $part = $campaign ? Participation::find((int) $account['id'], (int) $campaign['id']) : null;
        $link = $account ? ShortLink::forAccount((int) $account['id']) : null;
        View::render('dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'account' => $account,
            'campaign' => $campaign,
            'linked' => $link && $link['link_verified_at'] !== null,
            'clicks' => $campaign ? Click::validCount((int) $link['id'], (int) $campaign['id']) : 0,
            'posts' => $part ? count(Submission::forParticipation((int) $part['id'])) : 0,
        ], 'app');
    }
}
