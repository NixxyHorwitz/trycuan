<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\Ledger;
use App\Models\PayoutMethod;
use App\Models\Withdrawal;
use App\Services\PayoutException;
use App\Services\ProofFile;
use App\Services\WithdrawalService;

final class BalanceController
{
    public function index(): void
    {
        $this->show();
    }

    public function method(): void
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $provider = post('provider');
        $name = Validate::cleanName(post('account_name'));
        $number = (string) preg_replace('/[\s.-]+/', '', post('account_no'));
        $old = ['provider' => $provider, 'account_name' => $name, 'account_no' => post('account_no')];
        if (!RateLimiter::hit("payout-method:$id", 10, 3600)) {
            $this->show(['provider' => 'Terlalu banyak percobaan. Coba lagi dalam satu jam.'], $old, 429);
            return;
        }
        $errors = array_filter([
            'provider' => isset(PayoutMethod::PROVIDERS[$provider]) ? null : 'Pilih bank atau e-wallet.',
            'account_name' => Validate::name($name),
            'account_no' => preg_match('/^\d{6,20}$/D', $number) ? null : 'Nomor harus 6 sampai 20 angka.',
            // Rekening adalah tujuan uang, jadi sesi yang terbuka saja tidak cukup.
            'password' => password_verify(post('password'), $user['password_hash']) ? null : 'Password salah.',
        ]);
        if ($errors) {
            $this->show($errors, $old);
            return;
        }
        PayoutMethod::save($id, $provider, $name, $number);
        Session::flash('Rekening disimpan.');
        redirect('/saldo');
    }

    public function withdraw(): void
    {
        $id = (int) Auth::user()['id'];
        $raw = post('amount');
        if (!RateLimiter::hit("withdraw:$id", 20, 3600)) {
            $this->show(['amount' => 'Terlalu banyak percobaan. Coba lagi dalam satu jam.'], ['amount' => $raw], 429);
            return;
        }
        if (!preg_match('/^\d{1,12}$/D', $raw)) {
            $this->show(['amount' => 'Isi nominal dalam angka bulat.'], ['amount' => $raw]);
            return;
        }
        try {
            WithdrawalService::request($id, (int) $raw, post('token'));
        } catch (PayoutException $error) {
            $this->show(['amount' => $error->getMessage()], ['amount' => $raw]);
            return;
        }
        Session::flash('Penarikan diajukan. Admin akan mentransfer secara manual.');
        redirect('/saldo');
    }

    public function proof(int $id): void
    {
        $user = Auth::user();
        $row = Withdrawal::find($id);
        // 404 juga untuk milik orang lain supaya keberadaannya tidak bocor.
        if (!$row || (int) $row['user_id'] !== (int) $user['id'] || !$row['proof_file'] || !is_file(ProofFile::path($row['proof_file']))) {
            throw new HttpException(404, 'Bukti tidak ditemukan.');
        }
        ProofFile::send($row['proof_file']);
    }

    private function show(array $errors = [], array $old = [], int $errorStatus = 422): void
    {
        $id = (int) Auth::user()['id'];
        View::render('balance', [
            'title' => 'Saldo',
            'active' => 'balance',
            'balance' => Ledger::balance($id),
            'method' => PayoutMethod::forUser($id),
            'pending' => Withdrawal::pendingFor($id),
            'entries' => Ledger::forUser($id),
            'needsRefresh' => WithdrawalService::needsRefresh($id),
            'token' => bin2hex(random_bytes(16)),
            'errors' => $errors,
            'old' => $old,
        ], 'app', $errors ? $errorStatus : 200);
    }
}
