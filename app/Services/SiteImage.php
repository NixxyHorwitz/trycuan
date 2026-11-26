<?php
declare(strict_types=1);

namespace App\Services;

/** Gambar publik situs. Disusun ulang oleh GD, jadi isi selain gambar tidak ikut. */
final class SiteImage
{
    private const MAX = 3 * 1024 * 1024;
    private const SIDE = 1600;

    public static function store(array $file): string
    {
        $path = $file['tmp_name'] ?? '';
        if (!is_string($path) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($path) || filesize($path) > self::MAX) throw new PayoutException('Ukuran gambar maksimal 3 MB.');
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) throw new PayoutException('Gambar harus PNG, JPG, atau WebP.');
        if ($info[0] > 4000 || $info[1] > 4000) throw new PayoutException('Sisi gambar maksimal 4.000 piksel.');
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (!$image) throw new PayoutException('Gambar tidak bisa dibaca.');
        imagepalettetotruecolor($image);
        if (max($info[0], $info[1]) > self::SIDE) $image = imagescale($image, $info[0] >= $info[1] ? self::SIDE : (int) round($info[0] * self::SIDE / $info[1]));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $name = bin2hex(random_bytes(10)) . '.png';
        if (!is_dir(self::dir())) mkdir(self::dir(), 0755);
        if (!imagepng($image, self::dir() . "/$name", 8)) throw new \RuntimeException('Gagal menyimpan gambar');
        return $name;
    }

    public static function delete(string $name): void
    {
        if (self::valid($name)) @unlink(self::dir() . "/$name");
    }

    public static function url(string $name): string
    {
        return self::valid($name) ? "/media/$name" : '';
    }

    private static function valid(string $name): bool
    {
        return preg_match('/^[0-9a-f]{20}\.png$/D', $name) === 1;
    }

    private static function dir(): string
    {
        return ROOT . '/public/media';
    }
}
