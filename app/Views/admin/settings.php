<?php
use App\Models\Campaign;
use App\Models\Setting;
use App\Services\GoogleLogin;
use App\Services\SiteImage;

$isSeo = $active === 'seo';
$path = $isSeo ? '/admin/seo' : '/admin/pengaturan';
[$label, , $note, $fields] = Setting::groups()[$group];
$field = static function (string $key, array $spec) use ($errors, $old, $campaigns): void {
    [$kind, $label, , $more] = $spec;
    $value = $old[$key];
    $error = $errors[$key] ?? null;
    $hint = $more['hint'] ?? null;
    if ($kind === 'image') {
        $image = SiteImage::url(Setting::str($key)); ?>
<div class="field"><label for="<?= e($key) ?>"><?= e($label) ?></label>
  <?php if ($image): ?><div class="entry"><img class="thumb" src="<?= e($image) ?>" alt="<?= e($label) ?> saat ini"><label class="check items-center"><input type="checkbox" name="<?= e($key) ?>_hapus" value="1">Hapus gambar</label></div><?php endif ?>
  <div class="file"><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="file" data-max="3145728" accept="image/jpeg,image/png,image/webp"><?= icon('upload') ?><span><strong data-file="Pilih atau seret gambar">Pilih atau seret gambar</strong><span class="hint"><?= e(($hint ?? '') . ' Maksimal 3 MB.') ?></span></span></div>
  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif ?></div>
<?php return;
    }
    if ($kind === 'bool') { ?>
<div class="toggle-row"><div><label for="<?= e($key) ?>"><?= e($label) ?></label><?php if ($hint): ?><p class="hint"><?= e($hint) ?></p><?php endif ?></div>
  <input type="hidden" name="<?= e($key) ?>" value="0"><input class="toggle" id="<?= e($key) ?>" type="checkbox" name="<?= e($key) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?>></div>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif;
        return;
    }
    $optional = !in_array($kind, ['int', 'select', 'campaign'], true) && empty($more['min']);
    $type = ['int' => 'text', 'select' => 'select', 'campaign' => 'select', 'textarea' => 'textarea', 'secret' => 'password', 'email' => 'email', 'url' => 'url'][$kind] ?? 'text';
    $options = $more['options'] ?? [];
    if ($kind === 'campaign') {
        $options = ['0' => 'Belum dipilih'];
        foreach ($campaigns as $c) if ($c['status'] !== 'draft') $options[$c['id']] = $c['title'] . ' (' . Campaign::label(Campaign::state($c)) . ')';
    }
    $attrs = 'maxlength="' . (int) ($more['max'] ?? 300) . '"';
    if ($kind === 'int') $attrs = 'data-money data-plain inputmode="numeric" data-minimum="' . (int) $more['min'] . '" data-maximum="' . (int) $more['max'] . '"';
    if ($kind === 'textarea') $attrs .= ' rows="' . (int) ($more['rows'] ?? 3) . '"';
    if (in_array($kind, ['text', 'textarea'], true) && isset($more['max']) && $more['max'] <= 300) $attrs .= ' data-count';
    if ($kind === 'secret') {
        $auto = 'new-password';
        $match = null;
        $attrs .= ' data-novalid placeholder="' . (Setting::str($key) !== '' ? 'Tersimpan, isi untuk mengganti' : 'Belum diisi') . '"';
    }
    require ROOT . '/app/Views/partials/field.php';
    if ($kind === 'secret' && Setting::str($key) !== '') echo '<label class="check items-center"><input type="checkbox" name="' . e($key) . '_hapus" value="1">Hapus yang tersimpan</label>';
};
?>
<div class="page-head"><h1><?= e($title) ?></h1><p><?= $isSeo ? 'Tampilan situs di mesin pencari dan media sosial.' : 'Berlaku untuk seluruh situs.' ?></p></div>
<div class="settings">
  <nav class="settings-nav" aria-label="Kelompok pengaturan">
    <?php if ($isSeo): ?><a href="/admin/seo"><?= icon('activity') ?>Ringkasan</a><?php endif ?>
    <?php foreach (Setting::groups() as $key => [$name, $ic]): if (str_starts_with($key, 'seo-') !== $isSeo) continue; ?>
    <a href="<?= $path ?>?grup=<?= e($key) ?>"<?= $key === $group ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><?= e($name) ?></a>
    <?php endforeach ?>
  </nav>
  <div class="stack">
    <?php if (!empty($errors['form'])): ?><div class="notice bad mb-0!" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
    <form class="card" method="post" action="<?= $path ?>?grup=<?= e($group) ?>" enctype="multipart/form-data" data-once="Menyimpan...">
      <?= csrf_field() ?>
      <div class="mb-1"><h2><?= e($label) ?></h2><p class="hint"><?= e($note) ?></p></div>
      <?php foreach ($fields as $key => $spec) $field($key, $spec) ?>
      <div class="save-bar"><button class="btn primary" type="submit">Simpan perubahan</button></div>
    </form>
    <?php if ($group === 'google'): ?>
    <section class="card"><h2>Cara menyiapkan</h2>
      <ol class="num-list"><li>Buka Google Cloud Console, lalu buat OAuth client jenis <strong>Web application</strong>.</li><li>Isi layar persetujuan OAuth dengan nama situs dan email kontak.</li><li>Tambahkan alamat berikut ke <strong>Authorized redirect URIs</strong>.</li><li>Salin Client ID dan client secret ke kolom di atas, lalu aktifkan.</li></ol>
      <div class="copy-box mt-4"><div class="row"><span>Redirect URI</span><button class="text-btn" type="button" data-copy="redirect-uri"><?= icon('copy') ?>Salin</button></div><pre id="redirect-uri"><?= e(GoogleLogin::callback()) ?></pre></div>
    </section>
    <?php elseif ($group === 'email'): ?>
    <form class="card" method="post" action="/admin/pengaturan/tes-email" data-once="Mengirim..."><?= csrf_field() ?><h2>Tes pengiriman</h2><p class="hint mb-3">Mengirim satu email ke <?= e($user['email']) ?> memakai pengaturan tersimpan.</p><button class="btn outline" type="submit"><?= icon('send') ?><span>Kirim email tes</span></button></form>
    <?php elseif ($group === 'seo-sosial'): ?>
    <section class="card"><h2>Pratinjau saat dibagikan</h2><div class="share"><img src="<?= e(parse_url(App\Services\Seo::image(), PHP_URL_PATH)) ?>" alt="Gambar berbagi"><div><span><?= e(strtoupper((string) parse_url(App\Services\Seo::base(), PHP_URL_HOST))) ?></span><strong><?= e(App\Services\Seo::title(setting('seo_home_title'))) ?></strong><p><?= e(setting('seo_home_description')) ?></p></div></div></section>
    <?php elseif ($group === 'umum' && Setting::int('maintenance')): ?><div class="notice warn"><?= icon('alert') ?><span>Mode perawatan aktif. Freelancer tidak bisa membuka situs.</span></div>
    <?php endif ?>
  </div>
</div>
