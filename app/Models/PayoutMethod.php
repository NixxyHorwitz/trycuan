<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

final class PayoutMethod
{
    public const PROVIDERS = ['BCA' => 'BCA', 'BRI' => 'BRI', 'BNI' => 'BNI', 'MANDIRI' => 'Mandiri', 'BSI' => 'BSI', 'CIMB' => 'CIMB Niaga', 'PERMATA' => 'Permata',
        'JAGO' => 'Bank Jago', 'SEABANK' => 'SeaBank', 'DANA' => 'DANA', 'GOPAY' => 'GoPay', 'OVO' => 'OVO', 'SHOPEEPAY' => 'ShopeePay', 'LINKAJA' => 'LinkAja'];

    public static function forUser(int $userId): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM payout_methods WHERE user_id = ?');
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }

    public static function save(int $userId, string $provider, string $name, string $number): void
    {
        Database::pdo()->prepare('INSERT INTO payout_methods (user_id, provider, account_name, account_enc, account_hash) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE provider = VALUES(provider), account_name = VALUES(account_name), account_enc = VALUES(account_enc), account_hash = VALUES(account_hash)')
            ->execute([$userId, $provider, $name, Crypto::encrypt($number), Crypto::fingerprint("$provider:$number")]);
    }

    public static function masked(array $row): string
    {
        return '•••• ' . substr(Crypto::decrypt($row['account_enc']), -4);
    }
}
