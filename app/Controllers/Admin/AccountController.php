<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;
use App\Services\AccountTrustEvaluator;
use App\Services\FeeCredit;
use App\Services\HeldClicks;

/** Perbandingan views dan klik satu akun di satu campaign. */
final class AccountController
{
    public function detail(int $id): void
    {
        $this->show($id);
    }

    public function clicks(int $id): void
    {
        $part = Participation::byId($id) ?? throw new HttpException(404, 'Partisipasi tidak ditemukan.');
        $accept = ['terima' => true, 'buang' => false][post('keputusan')] ?? throw new HttpException(400, 'Keputusan tidak dikenal.');
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 300) {
            $this->show($id, 'Isi alasan, 3 sampai 300 karakter.');
            return;
        }
        $valid = HeldClicks::decide($part, $accept, (int) Auth::user()['id'], $reason);
        Session::flash($accept ? "$valid klik dinyatakan valid." : 'Klik ditahan dibuang.');
        redirect("/admin/akun/$id");
    }

    private function show(int $id, ?string $error = null): void
    {
        $part = Participation::byId($id) ?? throw new HttpException(404, 'Partisipasi tidak ditemukan.');
        $link = ShortLink::forAccount((int) $part['account_id']);
        View::render('admin/account', [
            'title' => '@' . $part['username'],
            'active' => 'fees',
            'part' => $part,
            'campaign' => Campaign::find((int) $part['campaign_id']),
            'trust' => AccountTrustEvaluator::evaluate($part),
            'credit' => FeeCredit::eligible($part),
            'views' => Submission::viewsByDay($id),
            'clicks' => Click::week((int) $link['id'], (int) $part['campaign_id']),
            'reasons' => Click::reasons((int) $link['id'], (int) $part['campaign_id']),
            'errors' => $error ? ['form' => $error] : [],
        ], 'admin', $error ? 422 : 200);
    }
}
