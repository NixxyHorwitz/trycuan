<?php
declare(strict_types=1);

namespace App\Services;

/** Bukti transfer dan screenshot insight, disimpan di luar public dengan nama acak. */
final class ProofFile
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const MAX = 2 * 1024 * 1024;

    public static function store(array $file, string $label = 'bukti transfer', int $max = self::MAX, bool $pdf = true): string
    {
        $path = $file['tmp_name'] ?? '';
        if (!is_string($path) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($path)) {
            throw new PayoutException(in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? self::tooBig($max) : "Unggah $label.");
        }
        $size = filesize($path);
        if (!$size || $size > $max) throw new PayoutException(self::tooBig($max));
        // Isi file yang menentukan jenisnya, bukan nama atau header dari pengirim.
        $extension = self::TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($path)] ?? '';
        if ($extension === '' || ($extension === 'pdf' && !$pdf)) throw new PayoutException('Berkas harus JPG, PNG, ' . ($pdf ? 'WebP, atau PDF.' : 'atau WebP.'));
        if ($extension !== 'pdf' && !@getimagesize($path)) throw new PayoutException('Gambar tidak bisa dibaca.');
        $name = bin2hex(random_bytes(16)) . ".$extension";
        if (!move_uploaded_file($path, self::path($name))) throw new \RuntimeException('Gagal menyimpan bukti');
        return $name;
    }

    private static function tooBig(int $max): string
    {
        return 'Ukuran berkas maksimal ' . intdiv($max, 1024 * 1024) . ' MB.';
    }

    public static function delete(string $name): void
    {
        @unlink(self::path($name));
    }

    public static function send(string $name, string $label = ''): void
    {
        $type = array_search(pathinfo($name, PATHINFO_EXTENSION), self::TYPES, true);
        header("Content-Type: $type");
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Disposition: ' . ($type === 'application/pdf' ? 'attachment' : 'inline') . '; filename="bukti.' . pathinfo($name, PATHINFO_EXTENSION) . '"' . ($label === '' ? '' : "; filename*=UTF-8''" . rawurlencode($label)));
        header('Content-Length: ' . filesize(self::path($name)));
        readfile(self::path($name));
    }

    public static function path(string $name): string
    {
        if (!preg_match('/^[0-9a-f]{32}\.(jpg|png|webp|pdf)$/D', $name)) throw new \InvalidArgumentException('Nama bukti tidak valid');
        return ROOT . '/storage/proofs/' . $name;
    }
}
