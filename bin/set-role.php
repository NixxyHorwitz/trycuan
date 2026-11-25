<?php
// Pakai: php bin/set-role.php email@contoh.com admin|freelancer
declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
[, $email, $role] = $argv + [null, '', ''];
if (!in_array($role, ['admin', 'freelancer'], true)) exit("Pakai: php bin/set-role.php email admin|freelancer\n");
$st = Database::pdo()->prepare('UPDATE users SET role = ?, session_version = session_version + 1 WHERE email = ?');
$st->execute([$role, strtolower(trim($email))]);
echo $st->rowCount() ? "Peran diubah menjadi $role. User perlu masuk lagi.\n" : "Email tidak ditemukan.\n";
