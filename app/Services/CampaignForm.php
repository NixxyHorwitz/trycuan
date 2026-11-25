<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;

/** Membaca dan memvalidasi form campaign admin. */
final class CampaignForm
{
    private const MAX_MONEY = 100_000_000_000;

    /** @return array{0: array, 1: array<string, string>} data untuk disimpan dan pesan error */
    public static function parse(array $in, ?array $current, bool $hasAccounts): array
    {
        $errors = [];
        $text = static fn (string $key): string => trim(str_replace("\r\n", "\n", $in[$key] ?? ''));

        $title = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $text('title')));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 100) $errors['title'] = 'Judul harus 3 sampai 100 karakter.';
        $brief = $text('brief');
        if (mb_strlen($brief) < 10 || mb_strlen($brief) > 5000) $errors['brief'] = 'Brief harus 10 sampai 5.000 karakter.';

        $url = $text('destination_url');
        $parts = parse_url($url) ?: [];
        if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'])) {
            $errors['destination_url'] = 'Isi URL lengkap yang diawali https://';
        }

        $rules = ['min_length' => 0];
        foreach (['required' => 'Kata wajib', 'banned' => 'Kata terlarang'] as $key => $label) {
            $rules[$key] = self::lines($text($key));
            foreach ($rules[$key] as $term) {
                if (mb_strlen($term) > 100 || !preg_match('/[\p{L}\p{N}]/u', $term)) $errors[$key] = "$label harus berisi huruf atau angka, maksimal 100 karakter per baris.";
            }
            if (count($rules[$key]) > 20) $errors[$key] = "$label maksimal 20 baris.";
        }
        if (!isset($errors['banned'])) {
            $both = array_intersect(array_map('mb_strtolower', $rules['required']), array_map('mb_strtolower', $rules['banned']));
            if ($both) $errors['banned'] = 'Ada kata yang wajib sekaligus terlarang: ' . reset($both);
        }
        $min = self::integer($text('min_length') === '' ? '0' : $text('min_length'), 0, 500);
        if ($min === null) $errors['min_length'] = 'Isi 0 sampai 500.';
        $rules['min_length'] = $min ?? 0;

        $money = [];
        foreach (['rate_per_1k' => 'Fee', 'cap_per_account' => 'Batas', 'budget' => 'Anggaran'] as $key => $label) {
            $money[$key] = self::integer($text($key), 1, self::MAX_MONEY);
            if ($money[$key] === null) $errors[$key] = "$label harus angka bulat lebih dari 0.";
        }
        if (!array_intersect_key($errors, $money)) {
            if ($money['cap_per_account'] < $money['rate_per_1k']) $errors['cap_per_account'] = 'Batas per akun minimal sebesar fee per 1.000 views.';
            elseif ($money['budget'] < $money['cap_per_account']) $errors['budget'] = 'Anggaran minimal sebesar batas per akun.';
        }

        $countries = array_values(array_unique(array_filter(preg_split('/[\s,]+/', strtoupper($text('target_countries'))) ?: [])));
        if (!$countries || count($countries) > 15 || preg_grep('/^[A-Z]{2}$/D', $countries, PREG_GREP_INVERT)) {
            $errors['target_countries'] = 'Isi kode negara dua huruf, misalnya ID atau ID, MY.';
        }

        $starts = self::date($text('starts_at'));
        $ends = self::date($text('ends_at'));
        if (!$starts) $errors['starts_at'] = 'Isi tanggal mulai.';
        if (!$ends) $errors['ends_at'] = 'Isi tanggal selesai.';
        elseif ($starts && $ends <= $starts) $errors['ends_at'] = 'Tanggal selesai harus setelah tanggal mulai.';

        $status = $text('status');
        if (!in_array($status, $current ? ['draft', 'active', 'paused', 'ended'] : ['draft', 'active'], true)) {
            $errors['status'] = 'Pilih status.';
        } elseif (in_array($status, ['active', 'paused'], true) && $ends && $ends->getTimestamp() <= time()) {
            $errors['ends_at'] ??= 'Tanggal selesai harus di masa depan.';
        } elseif ($hasAccounts && $status === 'draft') {
            $errors['status'] = 'Campaign sudah diikuti akun. Gunakan Dijeda atau Selesai.';
        }
        // Utas yang sudah masuk dinilai memakai tanggal mulai ini.
        if ($hasAccounts && $starts && $starts->format('Y-m-d H:i') !== substr((string) $current['starts_at'], 0, 16)) {
            $errors['starts_at'] = 'Tanggal mulai tidak bisa diubah setelah ada akun yang ikut.';
        }

        return [[
            'title' => $title,
            'brief' => $brief,
            'destination_url' => $url,
            'rules_json' => json_encode($rules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'target_countries' => implode(',', $countries),
            ...$money,
            'starts_at' => $starts?->format('Y-m-d H:i:s'),
            'ends_at' => $ends?->format('Y-m-d H:i:s'),
            'status' => $status,
        ], $errors];
    }

    /** Nilai awal form dari campaign tersimpan. */
    public static function values(?array $campaign): array
    {
        if (!$campaign) {
            return ['rate_per_1k' => '10000', 'status' => 'draft', 'min_length' => '0', 'target_countries' => 'ID', 'starts_at' => date('Y-m-d\TH:i')];
        }
        $rules = Campaign::rules($campaign);
        return [
            'required' => implode("\n", $rules['required']),
            'banned' => implode("\n", $rules['banned']),
            'min_length' => (string) $rules['min_length'],
            'starts_at' => date('Y-m-d\TH:i', strtotime($campaign['starts_at'])),
            'ends_at' => date('Y-m-d\TH:i', strtotime($campaign['ends_at'])),
        ] + array_map('strval', $campaign);
    }

    private static function lines(string $text): array
    {
        $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $line)), explode("\n", $text));
        return array_values(array_unique(array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    private static function integer(string $value, int $min, int $max): ?int
    {
        if (!preg_match('/^\d{1,12}$/D', $value)) return null;
        return (int) $value >= $min && (int) $value <= $max ? (int) $value : null;
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date : null;
    }
}
