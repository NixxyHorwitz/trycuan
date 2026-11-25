<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Threads\PostData;

/** Syarat campaign yang bisa dicek mesin. Sisanya dinilai admin. */
final class PostRules
{
    /**
     * @param array{required: string[], banned: string[], min_length: int} $rules
     * @return string[] pelanggaran; kosong berarti lolos
     */
    public static function violations(PostData $post, array $rules): array
    {
        $found = [];
        $length = mb_strlen(trim($post->text));
        if ($length < $rules['min_length']) $found[] = "Teks minimal {$rules['min_length']} karakter, utas kamu $length.";
        foreach ($rules['required'] as $term) {
            if (!self::contains($post->text, $term) && !self::isTopic($post->topic, $term)) $found[] = "Belum memuat: $term";
        }
        foreach ($rules['banned'] as $term) {
            if (self::contains($post->text, $term) || self::isTopic($post->topic, $term)) $found[] = "Memuat kata terlarang: $term";
        }
        return $found;
    }

    /** Kata utuh tanpa membedakan huruf besar dan kecil. */
    public static function contains(string $text, string $term): bool
    {
        $pattern = str_replace(' ', '\s+', preg_quote($term, '/'));
        // Untuk @nama, titik yang diikuti huruf masih bagian dari username lain.
        $end = str_starts_with($term, '@') ? '(?![\p{L}\p{N}_]|\.[\p{L}\p{N}_])' : '(?![\p{L}\p{N}_])';
        return preg_match('/(?<![\p{L}\p{N}_])' . $pattern . $end . '/iu', $text) === 1;
    }

    /** Topik Threads disimpan di luar teks dan tanpa tanda pagar. */
    private static function isTopic(string $topic, string $term): bool
    {
        return $topic !== '' && mb_strtolower($topic) === mb_strtolower(ltrim($term, '#'));
    }
}
