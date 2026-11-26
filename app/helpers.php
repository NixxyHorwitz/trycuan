<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string
{
    $file = ROOT . '/public/assets/' . $path;
    return '/assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">';
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function rupiah(int $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

/** Parameter URL berupa string yang sudah dirapikan. */
function query(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) && mb_check_encoding($value, 'UTF-8') ? trim($value) : '';
}

/** Input POST berupa string; array atau UTF-8 rusak menjadi string kosong. */
function post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : '';
}

/** Ikon Lucide (lisensi ISC), disimpan lokal. */
function icon(string $name): string
{
    static $paths = null;
    $paths ??= require ROOT . '/app/icons.php';
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function angka(int|string|null $value): string
{
    return number_format((int) $value, 0, ',', '.');
}

/** Atribut data-chart untuk canvas. */
function chart_attr(array $spec): string
{
    return 'data-chart="' . e(json_encode($spec, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) . '"';
}

/** Avatar DiceBear. Benih acak, jadi nama dan email tidak pernah dikirim. Huruf awal tampil bila gambar gagal. */
function avatar(array $user, string $class = ''): string
{
    $style = isset(App\Models\User::AVATARS[$user['avatar_style'] ?? '']) ? $user['avatar_style'] : 'thumbs';
    return '<span class="avatar ' . e($class) . '" aria-hidden="true">' . e(mb_substr($user['name'], 0, 1)) . ($user['avatar_seed'] === '' ? '' : '<img src="' . e(avatar_url($style, $user['avatar_seed'])) . '" alt="" width="64" height="64" referrerpolicy="no-referrer">') . '</span>';
}

function avatar_url(string $style, string $seed): string
{
    return "https://api.dicebear.com/10.x/$style/svg?seed=" . rawurlencode($seed);
}

/** Avatar menurut ID user, untuk daftar. */
function avatar_of(int|string|null $userId, string $name = '?'): string
{
    static $rows = [];
    $id = (int) $userId;
    $rows[$id] ??= App\Models\User::find($id) ?? ['name' => $name, 'avatar_seed' => ''];
    return avatar($rows[$id]);
}

/** Logo dan nama situs. */
function brand(string $label = ''): string
{
    $logo = App\Services\SiteImage::url(setting('site_logo'));
    return ($logo ? '<img class="mark" src="' . e($logo) . '" alt="" width="36" height="36">' : '<span class="mark">' . icon('trend') . '</span>') . '<span class="brand-name">' . e(setting('site_name')) . '</span>' . ($label ? '<small>' . e($label) . '</small>' : '');
}

function setting(string $key): string
{
    return App\Models\Setting::str($key);
}

function theme(): string
{
    $theme = $_COOKIE['trycuan_theme'] ?? '';
    return in_array($theme, ['light', 'dark'], true) ? $theme : setting('theme_default');
}
