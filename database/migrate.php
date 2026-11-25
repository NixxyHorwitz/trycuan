<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$name = Env::get('DB_NAME');
if (!preg_match('/^\w+$/', $name)) exit("DB_NAME tidak valid\n");
Database::connect(false)->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$db = Database::pdo();
$db->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$done = $db->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $migration = basename($file);
    if (in_array($migration, $done, true)) continue;
    foreach (array_filter(array_map('trim', explode(';', file_get_contents($file)))) as $sql) $db->exec($sql);
    $db->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$migration]);
    echo "Migrasi selesai: $migration\n";
}
echo "Database siap.\n";
