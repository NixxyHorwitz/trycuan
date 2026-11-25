<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\View;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Services\ParticipationManager;
use App\Services\SubmissionException;
use App\Services\SubmissionVerifier;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;
use App\Services\VerificationException;

final class CampaignController
{
    public function index(): void
    {
        $account = $this->account();
        View::render('campaigns/index', [
            'title' => 'Campaign',
            'active' => 'campaign',
            'campaigns' => Campaign::visible($account ? (int) $account['id'] : null),
        ], 'app');
    }

    public function detail(int $id): void
    {
        $this->show($id);
    }

    public function join(int $id): void
    {
        $account = $this->requireAccount();
        try {
            (new ParticipationManager())->join($account, $id);
        } catch (SubmissionException $error) {
            $this->show($id, ['form' => $error->getMessage()]);
            return;
        }
        Session::flash('Kamu ikut campaign ini.');
        redirect("/campaign/$id");
    }

    public function leave(int $id): void
    {
        $account = $this->requireAccount();
        try {
            (new ParticipationManager())->leave($account, $id);
        } catch (SubmissionException $error) {
            $this->show($id, ['form' => $error->getMessage()]);
            return;
        }
        Session::flash('Kamu keluar dari campaign.');
        redirect('/campaign');
    }

    public function submit(int $id): void
    {
        $account = $this->requireAccount();
        $url = trim(post('url'));
        if (!RateLimiter::hit("submit-gap:{$account['id']}", 1, 10)) {
            $this->show($id, ['url' => 'Tunggu 10 detik sebelum mengirim lagi.'], $url, 429);
            return;
        }
        if (!RateLimiter::hit("submit:{$account['id']}", 30, 3600)) {
            $this->show($id, ['url' => 'Terlalu banyak percobaan. Coba lagi dalam satu jam.'], $url, 429);
            return;
        }
        try {
            (new SubmissionVerifier(new ThreadsScraper()))->submit($account, $id, $url);
        } catch (SubmissionException | VerificationException $error) {
            $this->show($id, ['url' => $error->getMessage()], $url);
            return;
        } catch (ThreadsException $error) {
            $this->show($id, ['url' => $error->outcome->message()], $url);
            return;
        }
        Session::flash('Utas diterima dan menunggu persetujuan admin.');
        redirect("/campaign/$id");
    }

    private function account(): ?array
    {
        return ThreadsAccount::forUser((int) Auth::user()['id']);
    }

    private function requireAccount(): array
    {
        return $this->account() ?? redirect('/akun-threads');
    }

    private function show(int $id, array $errors = [], string $url = '', int $errorStatus = 422): void
    {
        $account = $this->account();
        $campaign = Campaign::find($id);
        $part = $account ? Participation::find((int) $account['id'], $id) : null;
        $state = $campaign ? Campaign::state($campaign) : 'draft';
        if ($state === 'draft' || (!$part && in_array($state, ['ended', 'paused'], true))) {
            throw new HttpException(404, 'Campaign tidak ditemukan atau sudah ditutup.');
        }
        $current = $account ? Participation::current((int) $account['id']) : null;
        View::render('campaigns/show', [
            'title' => $campaign['title'],
            'active' => 'campaign',
            'campaign' => $campaign,
            'state' => $state,
            'rules' => Campaign::rules($campaign),
            'account' => $account,
            'part' => $part,
            'linked' => $part && ShortLink::forAccount((int) $account['id'])['link_verified_at'] !== null,
            'busy' => $current && (int) $current['id'] !== $id ? $current : null,
            'posts' => $part ? Submission::forParticipation((int) $part['id']) : [],
            'errors' => $errors,
            'old' => ['url' => $url],
        ], 'app', $errors ? $errorStatus : 200);
    }
}
