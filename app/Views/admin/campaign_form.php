<?php
$field = static function (string $key, string $label, string $type, array $more = []) use ($errors, $old): void {
    $value = $old[$key] ?? '';
    $error = $errors[$key] ?? null;
    extract($more, EXTR_SKIP);
    require ROOT . '/app/Views/partials/field.php';
};
$statuses = ['draft' => 'Draf', 'active' => 'Aktif'] + ($campaign ? ['paused' => 'Dijeda', 'ended' => 'Selesai (permanen)'] : []);
if ($hasAccounts) unset($statuses['draft']);
?>
<div class="page-head"><a class="back" href="<?= $campaign ? '/admin/campaign/' . (int) $campaign['id'] . '/ringkasan' : '/admin/campaign' ?>"><?= icon('back') ?><?= $campaign ? 'Ringkasan campaign' : 'Semua campaign' ?></a><h1><?= e($title) ?></h1></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad narrow" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<form class="card max-w-4xl" method="post" action="<?= $campaign ? '/admin/campaign/' . (int) $campaign['id'] : '/admin/campaign/baru' ?>" data-once="Menyimpan...">
  <?= csrf_field() ?>
  <?php if ($closed): ?><p class="hint">Campaign sudah selesai dan tidak bisa diubah.</p><?php endif ?>
  <fieldset<?= $closed ? ' disabled' : '' ?>>
  <?php
  $field('title', 'Judul', 'text', ['attrs' => 'maxlength="100"']);
  $field('brief', 'Brief', 'textarea', ['attrs' => 'rows="6" maxlength="5000"']);
  $field('destination_url', 'URL tujuan', 'url', ['hint' => 'Tujuan shortlink freelancer, diawali https://', 'attrs' => 'maxlength="500"']);
  ?>
  <div class="pair">
  <?php
  $field('required', 'Wajib dimuat', 'textarea', ['hint' => 'Satu per baris: kata, #topik, atau @akun.', 'attrs' => 'rows="3"', 'optional' => true]);
  $field('banned', 'Dilarang dimuat', 'textarea', ['hint' => 'Satu per baris.', 'attrs' => 'rows="3"', 'optional' => true]);
  $field('rate_per_1k', 'Fee per 1.000 views', 'text', ['money' => true, 'attrs' => 'data-minimum="1" inputmode="numeric"'] + ($hasAccounts ? ['hint' => 'Perubahan hanya untuk akun yang baru ikut.'] : []));
  $field('cap_per_account', 'Maksimum per akun', 'text', ['money' => true, 'attrs' => 'data-minimum="1" inputmode="numeric"'] + ($hasAccounts ? ['hint' => 'Perubahan hanya untuk akun yang baru ikut.'] : []));
  $field('budget', 'Anggaran campaign', 'text', ['money' => true, 'attrs' => 'data-minimum="1" inputmode="numeric"']);
  $field('target_countries', 'Negara target', 'text', ['hint' => 'Kode dua huruf, pisahkan dengan koma.', 'attrs' => 'maxlength="60"']);
  $field('min_length', 'Panjang teks minimum', 'number', ['attrs' => 'min="0" max="500" step="1" inputmode="numeric"']);
  $field('starts_at', 'Mulai (WIB)', 'datetime-local', $hasAccounts ? ['attrs' => 'readonly', 'hint' => 'Terkunci karena sudah ada akun yang ikut.'] : []);
  $field('ends_at', 'Selesai (WIB)', 'datetime-local');
  ?>
  </div>
  <?php
  $field('status', 'Status', 'select', ['options' => $statuses, 'hint' => 'Draf tidak terlihat freelancer.']);
  ?>
  <button class="btn primary" type="submit">Simpan</button>
  </fieldset>
</form>
