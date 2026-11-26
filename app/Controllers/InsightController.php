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
use App\Models\Submission;
use App\Models\ThreadsAccount;
use App\Models\ViewReport;
use App\Services\ProofFile;
use App\Services\SubmissionException;
use App\Services\Threads\PostLink;
use App\Services\Threads\ThreadsException;
use App\Services\ViewReports;

/** Setor views dengan screenshot insight. */
final class InsightController
{
    public function form(int $id): void
    {
        [$account, $campaign, $part] = $this->context($id);
        $post = Submission::owned((int) query('utas'), (int) $account['id']);
        $mine = $post && (int) $post['participation_id'] === (int) $part['id'];
        $this->show($campaign, $part, [], ['url' => $mine ? "https://www.threads.com/@{$account['username']}/post/{$post['post_code']}" : '', 'views' => '']);
    }

    public function submit(int $id): void
    {
        [$account, $campaign, $part] = $this->context($id);
        $old = ['url' => mb_substr(trim(post('url')), 0, 500), 'views' => (string) preg_replace('/\D/', '', post('views'))];
        $errors = [];
        if (!ViewReports::open($campaign)) {
            $errors['form'] = 'Campaign ini tidak sedang menerima setoran views.';
        } elseif (!RateLimiter::hit("insight:{$account['id']}", 30, 3600)) {
            $errors['form'] = 'Terlalu banyak percobaan. Coba lagi dalam satu jam.';
        } else {
            try {
                $code = (new PostLink())->code($old['url']) ?? throw new SubmissionException(PostLink::HELP);
                ViewReports::submit($part, $code, strlen($old['views']) <= 12 ? (int) $old['views'] : 0, is_array($_FILES['bukti'] ?? null) ? $_FILES['bukti'] : []);
            } catch (SubmissionException $error) {
                $errors[$error->field] = $error->getMessage();
            } catch (ThreadsException $error) {
                $errors['url'] = PostLink::failure($error);
            }
        }
        if ($errors) {
            $this->show($campaign, $part, $errors, $old);
            return;
        }
        Session::flash('Setoran views diterima dan menunggu tinjauan admin.');
        redirect("/campaign/$id/setor");
    }

    public function proof(int $id): void
    {
        $report = ViewReport::owned($id, (int) Auth::user()['id']);
        if (!$report || !is_file(ProofFile::path($report['proof_file']))) throw new HttpException(404, 'Screenshot tidak ditemukan.');
        ProofFile::send($report['proof_file']);
    }

    /** @return array{array, array, array} */
    private function context(int $id): array
    {
        $account = ThreadsAccount::forUser((int) Auth::user()['id']) ?? redirect('/akun-threads');
        $campaign = Campaign::find($id);
        $part = $campaign ? Participation::find((int) $account['id'], $id) : null;
        if (!$part) throw new HttpException(404, 'Campaign tidak ditemukan atau belum kamu ikuti.');
        return [$account, $campaign, $part];
    }

    private function show(array $campaign, array $part, array $errors, array $old): void
    {
        View::render('campaigns/insight', [
            'title' => 'Setor views',
            'active' => 'campaign',
            'campaign' => $campaign,
            'open' => ViewReports::open($campaign),
            'reports' => ViewReport::forParticipation((int) $part['id']),
            'errors' => $errors,
            'old' => $old,
        ], 'app', $errors ? 422 : 200);
    }
}
