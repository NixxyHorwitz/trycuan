<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Ledger;
use App\Models\PayoutMethod;
use App\Models\Setting;
use App\Models\Withdrawal;

final class WithdrawalService
{
    private const FRESH = 3600;

    /** Saldo langsung ditahan. Token yang sama hanya membuat satu penarikan. */
    public static function request(int $userId, int $amount, string $token): void
    {
        if (!Setting::int('withdraw_open')) throw new PayoutException('Penarikan sedang ditutup sementara.');
        if (!preg_match('/^[0-9a-f]{32}$/D', $token)) throw new PayoutException('Formulir tidak valid. Muat ulang halaman.');
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            // Baris user dikunci: dua permintaan tidak bisa memakai saldo yang sama.
            $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$userId]);
            $st = $db->prepare('SELECT 1 FROM withdrawals WHERE user_id = ? AND request_token = ?');
            $st->execute([$userId, $token]);
            if ($st->fetchColumn()) {
                $db->commit();
                return;
            }
            $method = PayoutMethod::forUser($userId) ?? throw new PayoutException('Isi rekening pencairan dulu.');
            if (Withdrawal::pendingFor($userId)) throw new PayoutException('Masih ada penarikan yang diproses.');
            $minimum = Setting::int('min_withdrawal');
            if ($amount < $minimum) throw new PayoutException('Minimum penarikan ' . rupiah($minimum) . '.');
            if ($amount > Ledger::balance($userId)) throw new PayoutException('Saldo tidak cukup.');
            if (self::needsRefresh($userId)) throw new PayoutException('Buka dashboard dan tekan Refresh dulu supaya views terbaru terbaca.');
            $db->prepare('INSERT INTO withdrawals (user_id, request_token, amount, provider, account_name, account_enc, account_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $token, $amount, $method['provider'], $method['account_name'], $method['account_enc'], $method['account_hash']]);
            $id = (int) $db->lastInsertId();
            Ledger::add($userId, 'withdrawal', -$amount, "wd:$id", null, $id);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    /** @param array $file entri $_FILES */
    public static function pay(int $id, int $adminId, array $file): void
    {
        $proof = ProofFile::store($file);
        try {
            self::process($id, static function (array $row) use ($adminId, $proof): void {
                Database::pdo()->prepare("UPDATE withdrawals SET status = 'paid', proof_file = ?, processed_by = ?, processed_at = NOW() WHERE id = ?")->execute([$proof, $adminId, $row['id']]);
            }, $adminId, 'withdrawal.paid');
        } catch (\Throwable $error) {
            ProofFile::delete($proof);
            throw $error;
        }
    }

    public static function reject(int $id, int $adminId, string $reason): void
    {
        self::process($id, static function (array $row) use ($adminId, $reason): void {
            Database::pdo()->prepare("UPDATE withdrawals SET status = 'rejected', reason = ?, processed_by = ?, processed_at = NOW() WHERE id = ?")->execute([$reason, $adminId, $row['id']]);
            Ledger::add((int) $row['user_id'], 'refund', (int) $row['amount'], "wd-refund:{$row['id']}", null, (int) $row['id']);
        }, $adminId, 'withdrawal.rejected');
    }

    /** Ada utas yang dihitung tetapi belum dicoba baca dalam satu jam terakhir. */
    public static function needsRefresh(int $userId): bool
    {
        $st = Database::pdo()->prepare("SELECT s.last_checked_at, s.last_attempt_at, c.status, c.ends_at FROM submissions s
            JOIN participations p ON p.id = s.participation_id JOIN campaigns c ON c.id = p.campaign_id JOIN threads_accounts a ON a.id = p.account_id
            WHERE a.user_id = ? AND s.status = 'approved'");
        $st->execute([$userId]);
        foreach ($st as $row) {
            if (!ViewRefresher::allowed($row)) continue;
            if (max(strtotime($row['last_checked_at']), strtotime($row['last_attempt_at'] ?? '1970-01-02')) < time() - self::FRESH) return true;
        }
        return false;
    }

    private static function process(int $id, callable $run, int $adminId, string $action): void
    {
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $row = Withdrawal::find($id, true) ?? throw new PayoutException('Penarikan tidak ditemukan.');
            if ($row['status'] !== 'pending') throw new PayoutException('Penarikan ini sudah diproses.');
            $run($row);
            $db->prepare('INSERT INTO audit_logs (actor_id, action, target, meta_json) VALUES (?, ?, ?, ?)')
                ->execute([$adminId, $action, "withdrawal:$id", json_encode(['amount' => (int) $row['amount']], JSON_THROW_ON_ERROR)]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
