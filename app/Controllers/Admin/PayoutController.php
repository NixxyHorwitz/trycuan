<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Models\PayoutMethod;
use App\Models\Withdrawal;
use App\Services\Csv;
use App\Services\FeeCredit;
use App\Services\PayoutException;
use App\Services\ProofFile;
use App\Services\WithdrawalService;

final class PayoutController
{
    public function fees(?int $id = null): void
    {
        $this->showFees();
    }

    public function decideFee(int $id): void
    {
        $admin = (int) Auth::user()['id'];
        $reason = self::reason();
        try {
            switch (post('keputusan')) {
                case 'kreditkan':
                    FeeCredit::approve($id, (int) post('nominal'), $admin);
                    Session::flash('Fee masuk ke saldo freelancer.');
                    break;
                case 'tolak':
                    if ($reason === '') throw new PayoutException('Isi alasan, 3 sampai 300 karakter.');
                    FeeCredit::setTrust($id, 'rejected', $admin, $reason);
                    Session::flash('Fee yang belum cair dibatalkan.');
                    break;
                case 'pulihkan':
                    FeeCredit::setTrust($id, 'review', $admin, $reason);
                    Session::flash('Akun dipulihkan.');
                    break;
                default:
                    throw new HttpException(400, 'Keputusan tidak dikenal.');
            }
        } catch (PayoutException $error) {
            $this->showFees([$id => $error->getMessage()]);
            return;
        }
        redirect('/admin/fee');
    }

    public function withdrawals(?int $id = null): void
    {
        $this->showWithdrawals();
    }

    public function process(int $id): void
    {
        $admin = (int) Auth::user()['id'];
        try {
            switch (post('keputusan')) {
                case 'bayar':
                    WithdrawalService::pay($id, $admin, is_array($_FILES['bukti'] ?? null) ? $_FILES['bukti'] : []);
                    Session::flash('Penarikan ditandai dibayar.');
                    break;
                case 'tolak':
                    $reason = self::reason();
                    if ($reason === '') throw new PayoutException('Isi alasan, 3 sampai 300 karakter.');
                    WithdrawalService::reject($id, $admin, $reason);
                    Session::flash('Penarikan ditolak dan saldo dikembalikan.');
                    break;
                default:
                    throw new HttpException(400, 'Keputusan tidak dikenal.');
            }
        } catch (PayoutException $error) {
            $this->showWithdrawals([$id => $error->getMessage()]);
            return;
        }
        redirect('/admin/penarikan');
    }

    public function proof(int $id): void
    {
        $row = Withdrawal::find($id);
        if (!$row || !$row['proof_file'] || !is_file(ProofFile::path($row['proof_file']))) throw new HttpException(404, 'Bukti tidak ditemukan.');
        ProofFile::send($row['proof_file']);
    }

    /** Nomor rekening tetap disamarkan di berkas ekspor. */
    public function export(): void
    {
        $rows = Withdrawal::forAdmin('', 5000);
        Audit::add((int) Auth::user()['id'], 'withdrawal.exported', 'withdrawals', ['rows' => count($rows)]);
        Csv::send('penarikan', ['ID', 'Nama', 'Threads', 'Nominal', 'Tujuan', 'Nomor', 'Pemilik', 'Status', 'Diajukan', 'Diproses', 'Alasan'], array_map(static fn (array $w): array => [
            $w['id'], $w['name'], $w['username'], $w['amount'], PayoutMethod::PROVIDERS[$w['provider']] ?? $w['provider'], PayoutMethod::masked($w), $w['account_name'], $w['status'], $w['created_at'], $w['processed_at'], $w['reason'],
        ], $rows));
    }

    private static function reason(): string
    {
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        return mb_strlen($reason) >= 3 && mb_strlen($reason) <= 300 ? $reason : '';
    }

    private function showFees(array $errors = []): void
    {
        View::render('admin/fees', ['title' => 'Pencairan fee', 'active' => 'fees', 'rows' => FeeCredit::queue(), 'tab' => $errors ? '' : query('status'), 'errors' => $errors], 'admin', $errors ? 422 : 200);
    }

    private function showWithdrawals(array $errors = []): void
    {
        $tab = in_array(query('status'), ['paid', 'rejected'], true) ? query('status') : ($errors ? '' : 'pending');
        View::render('admin/withdrawals', ['title' => 'Penarikan', 'active' => 'withdrawals', 'rows' => Withdrawal::forAdmin($tab), 'tab' => $tab, 'counts' => Withdrawal::counts(), 'errors' => $errors], 'admin', $errors ? 422 : 200);
    }
}
