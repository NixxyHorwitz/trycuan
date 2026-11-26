<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\ViewReport;
use App\Services\ProofFile;
use App\Services\SubmissionException;
use App\Services\ViewReports;

/** Tinjauan views dari screenshot insight. */
final class InsightController
{
    public function index(?int $id = null): void
    {
        $this->show();
    }

    public function decide(int $id): void
    {
        $admin = (int) Auth::user()['id'];
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        $views = (string) preg_replace('/\D/', '', post('views'));
        try {
            switch (post('keputusan')) {
                case 'setujui':
                    ViewReports::approve($id, strlen($views) <= 12 ? (int) $views : 0, $admin);
                    Session::flash('Views disetujui dan fee dihitung ulang.');
                    break;
                case 'tolak':
                    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 300) throw new SubmissionException('Isi alasan penolakan, 3 sampai 300 karakter.');
                    ViewReports::reject($id, $reason, $admin);
                    Session::flash('Setoran ditolak.');
                    break;
                default:
                    throw new HttpException(400, 'Keputusan tidak dikenal.');
            }
        } catch (SubmissionException $error) {
            $this->show([$id => $error->getMessage()]);
            return;
        }
        redirect('/admin/insight');
    }

    public function proof(int $id): void
    {
        $report = ViewReport::find($id);
        if (!$report || !is_file(ProofFile::path($report['proof_file']))) throw new HttpException(404, 'Screenshot tidak ditemukan.');
        ProofFile::send($report['proof_file']);
    }

    private function show(array $errors = []): void
    {
        $tab = in_array(query('status'), ['approved', 'rejected'], true) ? query('status') : ($errors ? '' : 'pending');
        View::render('admin/insight', ['title' => 'Setoran views', 'active' => 'insight', 'rows' => ViewReport::forReview($tab), 'tab' => $tab, 'counts' => ViewReport::counts(), 'errors' => $errors], 'admin', $errors ? 422 : 200);
    }
}
