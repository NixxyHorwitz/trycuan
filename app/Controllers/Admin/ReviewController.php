<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Models\Submission;
use App\Services\AccountFeeCalculator;

final class ReviewController
{
    public function index(?int $id = null): void
    {
        $this->show();
    }

    public function decide(int $id): void
    {
        $submission = Submission::find($id) ?? throw new HttpException(404, 'Utas tidak ditemukan.');
        $status = ['setujui' => 'approved', 'tolak' => 'rejected'][post('keputusan')] ?? throw new HttpException(400, 'Keputusan tidak dikenal.');
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        if ($status === 'rejected' && (mb_strlen($reason) < 3 || mb_strlen($reason) > 300)) {
            $this->show(["alasan-$id" => 'Isi alasan penolakan, 3 sampai 300 karakter.'], [$id => $reason]);
            return;
        }
        self::apply($submission, $status, $reason);
        Session::flash($status === 'approved' ? 'Utas disetujui.' : 'Utas ditolak.');
        redirect('/admin/utas');
    }

    /** Keputusan yang sama untuk beberapa utas; hanya yang masih menunggu. */
    public function bulk(): void
    {
        $status = ['setujui' => 'approved', 'tolak' => 'rejected'][post('keputusan')] ?? throw new HttpException(400, 'Keputusan tidak dikenal.');
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        $ids = is_array($_POST['ids'] ?? null) ? array_map(static fn ($x): int => is_string($x) ? (int) $x : 0, $_POST['ids']) : [];
        $ids = array_slice(array_unique(array_filter($ids)), 0, 100);
        if (!$ids) {
            $this->show(['form' => 'Pilih minimal satu utas.']);
            return;
        }
        if ($status === 'rejected' && (mb_strlen($reason) < 3 || mb_strlen($reason) > 300)) {
            $this->show(['form' => 'Isi alasan penolakan, 3 sampai 300 karakter.']);
            return;
        }
        $done = 0;
        foreach ($ids as $id) {
            $submission = Submission::find($id);
            if (!$submission || $submission['status'] !== 'pending') continue;
            self::apply($submission, $status, $reason);
            $done++;
        }
        Session::flash($done . ' utas ' . ($status === 'approved' ? 'disetujui.' : 'ditolak.'));
        redirect('/admin/utas');
    }

    private static function apply(array $submission, string $status, string $reason): void
    {
        $id = (int) $submission['id'];
        $admin = (int) Auth::user()['id'];
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE submissions SET status = ?, reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, $status === 'rejected' ? $reason : null, $admin, $id]);
            Audit::add($admin, "submission.$status", "submission:$id", ['before' => $submission['status'], 'reason' => $status === 'rejected' ? $reason : null]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        AccountFeeCalculator::recalculate((int) $submission['participation_id']);
    }

    private function show(array $errors = [], array $old = []): void
    {
        View::render('admin/reviews', [
            'title' => 'Review utas',
            'active' => 'reviews',
            'tab' => $tab = in_array(query('status'), ['approved', 'rejected'], true) ? query('status') : 'pending',
            'counts' => Submission::counts(),
            // Error satu utas: tampilkan semua status supaya utasnya pasti terlihat.
            'posts' => Submission::forReview($errors && !isset($errors['form']) ? null : $tab),
            'errors' => $errors,
            'old' => $old,
        ], 'admin', $errors ? 422 : 200);
    }
}
