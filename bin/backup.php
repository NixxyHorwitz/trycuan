<?php
// Cadangkan database dan bukti transfer ke storage/backups: php bin/backup.php [hari_simpan]
declare(strict_types=1);

use App\Core\Env;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';

$keep = max(1, (int) ($argv[1] ?? 14));
$dir = ROOT . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0700)) exit("Folder backup tidak bisa dibuat.\n");
$stamp = date('Ymd-His');

// Password lewat file sementara, bukan argumen, supaya tidak terlihat di daftar proses.
$config = tempnam(sys_get_temp_dir(), 'tcb');
chmod($config, 0600);
$quote = static fn (string $v): string => '"' . addcslashes($v, "\\\"") . '"';
file_put_contents($config, "[client]\nhost=" . $quote(Env::get('DB_HOST', '127.0.0.1')) . "\nport=" . $quote(Env::get('DB_PORT', '3306')) . "\nuser=" . $quote(Env::get('DB_USER')) . "\npassword=" . $quote(Env::get('DB_PASS')) . "\n");
$target = "$dir/db-$stamp.sql.gz";
$process = proc_open([Env::get('MYSQLDUMP', 'mysqldump'), "--defaults-extra-file=$config", '--single-transaction', '--no-tablespaces', '--hex-blob', '--set-gtid-purged=OFF', '--default-character-set=utf8mb4', Env::get('DB_NAME')],
    [1 => ['pipe', 'w'], 2 => STDERR], $pipes);
$last = '';
if (is_resource($process)) {
    $gz = gzopen("$target.part", 'wb6');
    while (!feof($pipes[1])) {
        $chunk = (string) fread($pipes[1], 65536);
        gzwrite($gz, $chunk);
        $last = substr($last . $chunk, -200);
    }
    gzclose($gz);
    fclose($pipes[1]);
    $code = proc_close($process);
}
unlink($config);
// mysqldump menulis penanda di akhir; tanpa itu file terpotong.
if (!is_resource($process) && !isset($code) || $code !== 0 || !str_contains($last, 'Dump completed')) {
    @unlink("$target.part");
    fwrite(STDERR, "Backup database gagal. Periksa MYSQLDUMP di .env.\n");
    exit(1);
}
rename("$target.part", $target);
chmod($target, 0600);
echo 'Database: ' . basename($target) . ' (' . number_format(filesize($target) / 1024, 1) . " KB)\n";

$proofs = glob(ROOT . '/storage/proofs/*.{jpg,png,webp,pdf}', GLOB_BRACE) ?: [];
if ($proofs) {
    // Tar tanpa kompresi: gambar dan PDF sudah terkompresi.
    $tar = new PharData("$dir/bukti-$stamp.tar");
    foreach ($proofs as $file) $tar->addFile($file, basename($file));
    if (count($tar) !== count($proofs)) {
        fwrite(STDERR, "Backup bukti gagal.
");
        exit(1);
    }
    echo 'Bukti transfer: ' . count($proofs) . " file\n";
}
foreach (glob("$dir/{db-*.sql.gz,bukti-*.tar}", GLOB_BRACE) ?: [] as $file) {
    if (filemtime($file) < time() - $keep * 86400) unlink($file);
}
echo "APP_KEY tidak ikut. Simpan .env terpisah; tanpa APP_KEY nomor rekening tidak bisa dibaca.\n";
