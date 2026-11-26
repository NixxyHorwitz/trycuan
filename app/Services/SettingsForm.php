<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validate;
use App\Models\Audit;
use App\Models\Campaign;
use App\Models\Setting;

/** Memvalidasi dan menyimpan satu kelompok pengaturan. */
final class SettingsForm
{
    public static function values(string $group): array
    {
        $values = [];
        foreach (Setting::groups()[$group][3] as $key => [$type]) $values[$key] = $type === 'secret' ? '' : Setting::str($key);
        return $values;
    }

    /** @return array{0: array, 1: array<string, string>} isian dan pesan error; kosong berarti tersimpan */
    public static function save(string $group, int $adminId): array
    {
        $fields = Setting::groups()[$group][3];
        $values = $errors = [];
        foreach ($fields as $key => [$type, $label, , $more]) {
            if ($type === 'image') continue;
            $value = trim(str_replace("\r\n", "\n", post($key)));
            if ($type !== 'textarea') $value = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', $value));
            if ($type === 'int') $value = (string) preg_replace('/\D/', '', $value);
            $values[$key] = $value;
            $error = self::check($type, $value, $more);
            if ($error) $errors[$key] = $error;
        }
        $errors += self::together($group, $values);
        if ($errors) return [$values, $errors];

        $images = [];
        foreach ($fields as $key => [$type]) {
            if ($type !== 'image') continue;
            try {
                if (post("{$key}_hapus") === '1') $images[$key] = '';
                elseif (($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $images[$key] = SiteImage::store($_FILES[$key]);
            } catch (PayoutException $error) {
                return [$values, [$key => $error->getMessage()]];
            }
        }
        $changed = [];
        foreach ($values + $images as $key => $value) {
            $type = $fields[$key][0];
            // Kolom rahasia kosong berarti tidak diganti.
            if ($type === 'secret' && $value === '') {
                if (post("{$key}_hapus") !== '1') continue;
            } elseif (Setting::str($key) === $value) continue;
            $before = Setting::str($key);
            $changed[$key] = $type === 'secret' ? ['after' => 'diganti'] : ['before' => mb_substr($before, 0, 300), 'after' => mb_substr($value, 0, 300)];
            Setting::set($key, $value);
            if ($type === 'image' && $before !== '') SiteImage::delete($before);
        }
        if ($changed) Audit::add($adminId, 'settings.updated', "settings:$group", $changed);
        return [$values, []];
    }

    private static function check(string $type, string $value, array $more): ?string
    {
        $length = mb_strlen($value);
        if ($type === 'int') {
            return preg_match('/^\d{1,9}$/D', $value) && (int) $value >= $more['min'] && (int) $value <= $more['max'] ? null
                : 'Isi ' . angka($more['min']) . ' sampai ' . angka($more['max']) . '.';
        }
        if ($type === 'bool') return in_array($value, ['0', '1'], true) ? null : 'Pilih salah satu.';
        if ($type === 'select') return isset($more['options'][$value]) ? null : 'Pilih salah satu.';
        if ($type === 'campaign') return $value === '0' || (preg_match('/^\d{1,18}$/D', $value) && Campaign::find((int) $value)) ? null : 'Campaign tidak ditemukan.';
        if ($type === 'email' && $value !== '') return Validate::email($value);
        if ($type === 'url' && $value !== '') {
            $parts = parse_url($value) ?: [];
            return $length <= 300 && filter_var($value, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https' && !isset($parts['user']) ? null : 'Isi URL lengkap yang diawali https://';
        }
        if ($length < ($more['min'] ?? 0)) return "Minimal {$more['min']} karakter.";
        $max = $more['max'] ?? 300;
        return $length > $max ? 'Maksimal ' . angka($max) . ' karakter.' : null;
    }

    private static function together(string $group, array $v): array
    {
        if ($group === 'google') {
            $id = preg_match('/^[\w.-]+\.apps\.googleusercontent\.com$/D', $v['google_id']) === 1;
            if ($v['google_id'] !== '' && !$id) return ['google_id' => 'Client ID harus berakhiran .apps.googleusercontent.com'];
            if ($v['google_on'] === '1') {
                if (!$id) return ['google_id' => 'Isi Client ID sebelum mengaktifkan.'];
                if ($v['google_secret'] === '' && (Setting::str('google_secret') === '' || post('google_secret_hapus') === '1')) return ['google_secret' => 'Isi client secret sebelum mengaktifkan.'];
            }
        }
        if ($group === 'campaign' && $v['campaign_mode'] === 'focus') {
            $campaign = Campaign::find((int) $v['campaign_focus']);
            if (!$campaign || $campaign['status'] === 'draft') return ['campaign_focus' => 'Pilih campaign yang sudah aktif.'];
        }
        if ($group === 'seo-sosial' && $v['x_handle'] !== '' && !preg_match('/^@\w{1,15}$/D', $v['x_handle'])) return ['x_handle' => 'Tulis seperti @namaakun.'];
        if ($group === 'email' && $v['smtp_host'] !== '') {
            if (!preg_match('/^[a-z0-9.-]+$/iD', $v['smtp_host'])) return ['smtp_host' => 'Isi nama host saja, tanpa https://'];
            if ($v['mail_from'] === '') return ['mail_from' => 'Isi alamat pengirim.'];
        }
        if ($group === 'beranda') {
            foreach (explode("\n", $v['faq']) as $line) if (trim($line) !== '' && substr_count($line, '|') < 1) return ['faq' => 'Setiap baris butuh tanda | di antara pertanyaan dan jawaban.'];
        }
        return [];
    }
}
