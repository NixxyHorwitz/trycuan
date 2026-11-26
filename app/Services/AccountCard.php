<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\Click;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;

/** Data card akun untuk dashboard. */
final class AccountCard
{
    public static function build(array $account, int $campaignId = 0): ?array
    {
        $part = ($campaignId ? Participation::find((int) $account['id'], $campaignId) : null) ?? Participation::latest((int) $account['id']);
        if (!$part) return null;
        $campaign = Campaign::find((int) $part['campaign_id']);
        $posts = Submission::forParticipation((int) $part['id']);
        $link = ShortLink::forAccount((int) $account['id']);
        $open = ViewRefresher::allowed($campaign);
        $counted = array_filter($posts, static fn (array $p): bool => $p['status'] === 'approved' && $p['missing_at'] === null);
        $active = array_filter($posts, static fn (array $p): bool => $p['status'] !== 'rejected');
        $views = (int) $part['total_views'];
        $cap = (int) $part['cap_snapshot'];
        $calculated = AccountFeeCalculator::total($views, (int) $part['rate_snapshot'], $cap);
        return [
            'account' => $account,
            'part' => $part,
            'campaign' => $campaign,
            'state' => Campaign::state($campaign),
            'link' => $link,
            'posts' => $posts,
            'counted' => count($counted),
            'refreshable' => $open && $active !== [],
            'stale' => ViewRefresher::due((int) $account['id'], ViewRefresher::STALE),
            'partial' => array_filter($active, static fn (array $p): bool => $p['last_error'] !== null) !== [],
            'updated' => $active ? min(array_column($active, 'last_checked_at')) : null,
            'clicks' => Click::validCount((int) $link['id'], (int) $campaign['id']),
            'clickDays' => array_values(Click::week((int) $link['id'], (int) $campaign['id'])),
            'viewDays' => Submission::viewsByDay((int) $part['id']),
            'credit' => FeeCredit::eligible($part),
            'capped' => $calculated >= $cap,
            'budgetHit' => (int) $part['fee_total'] < $calculated,
        ];
    }

    /** Titik polyline untuk kotak 120 x 32. */
    public static function points(array $values): string
    {
        $max = max(1, ...$values);
        $last = max(1, count($values) - 1);
        $points = [];
        foreach (array_values($values) as $i => $value) $points[] = round($i / $last * 116 + 2, 1) . ',' . round(30 - $value / $max * 28, 1);
        return implode(' ', $points);
    }
}
