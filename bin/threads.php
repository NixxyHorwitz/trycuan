<?php
// Cek scraper: php bin/threads.php post URL_UTAS  |  php bin/threads.php profile USERNAME
declare(strict_types=1);

use App\Services\Threads\Identifier;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;

if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/bootstrap.php';
[, $type, $value] = $argv + [null, '', ''];
$id = match ($type) {
    'post' => Identifier::codeFromUrl($value),
    'profile' => Identifier::username($value),
    default => null,
};
if ($id === null) {
    fwrite(STDERR, "Input tidak valid. Pakai: post URL_UTAS atau profile USERNAME\n");
    exit(2);
}
try {
    $source = new ThreadsScraper();
    echo json_encode($type === 'post' ? $source->post($id) : $source->profile($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
} catch (ThreadsException $error) {
    fwrite(STDERR, "{$error->outcome->value}: {$error->getMessage()}\n");
    exit(1);
}
