<?php
// Kelola akun admin:
//   php bin/admin.php list
//   php bin/admin.php buat EMAIL "Nama"
//   php bin/admin.php password EMAIL
//   php bin/admin.php aktif|nonaktif EMAIL
// Password dibuat acak dan ditampilkan sekali. Ganti setelah masuk.
declare(strict_types=1);

use App\Models\User;
use App\Services\AdminTeam;
use App\Services\VerificationException;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

[, $do, $email, $name] = $argv + [null, '', '', ''];
$email = strtolower(trim($email));
$make = static fn (): string => bin2hex(random_bytes(10));
try {
    if ($do === 'list') {
        foreach (AdminTeam::all() as $a) echo "{$a['email']}  {$a['name']}  " . ($a['status'] === 'active' ? 'aktif' : 'nonaktif') . "\n";
        exit;
    }
    if (!in_array($do, ['buat', 'password', 'aktif', 'nonaktif'], true) || $email === '') exit("Pakai: php bin/admin.php list | buat EMAIL \"Nama\" | password EMAIL | aktif EMAIL | nonaktif EMAIL\n");
    if ($do === 'buat') {
        $password = $make();
        AdminTeam::create($name !== '' ? $name : 'Admin', $email, $password, null);
        exit("Admin dibuat. Masuk di /admin/masuk\nPassword: $password\n");
    }
    $row = User::findByEmail($email) ?? throw new VerificationException('Admin tidak ditemukan.');
    if ($do === 'password') {
        $password = $make();
        AdminTeam::setPassword((int) $row['id'], $password, null);
        exit("Password baru: $password\n");
    }
    AdminTeam::setStatus((int) $row['id'], $do === 'aktif' ? 'active' : 'blocked', null);
    echo "Admin sekarang $do.\n";
} catch (VerificationException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
