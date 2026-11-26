<?php
$confirm = static function (string $id): void { ?>
<div class="field"><label for="<?= e($id) ?>">Password kamu</label><div class="input-wrap"><input id="<?= e($id) ?>" name="konfirmasi" type="password" data-novalid autocomplete="current-password" required><button class="reveal" type="button" data-reveal="<?= e($id) ?>" aria-pressed="false" aria-label="Lihat password"><?= icon('eye') ?><?= icon('eyeoff') ?></button></div><p class="hint">Diminta setiap kali mengubah akun admin.</p></div>
<?php };
?>
<div class="page-head row"><div><h1>Tim admin</h1><p>Akun admin terpisah dari akun freelancer.</p></div><button class="btn primary" type="button" data-open="add"><?= icon('plus') ?>Tambah admin</button></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<div class="cards">
  <?php foreach ($admins as $a): $id = (int) $a['id']; $me = $id === (int) $user['id']; $on = $a['status'] === 'active'; ?>
  <section class="card">
    <div class="row"><div class="entry"><?= avatar($a) ?><div><h2><?= e($a['name']) ?></h2><p class="hint"><?= e($a['email']) ?></p></div></div><span class="tag<?= $on ? ' good' : ' fail' ?>"><?= $on ? ($me ? 'Kamu' : 'Aktif') : 'Nonaktif' ?></span></div>
    <p class="hint mt-3">Masuk terakhir <?= $a['last_login_at'] ? e(date('d/m/Y H:i', strtotime($a['last_login_at']))) : 'belum pernah' ?></p>
    <div class="actions mt-4"><?php if ($me): ?><a class="btn outline small" href="/admin/akun-saya">Akun saya</a><?php else: ?><button class="btn outline small" type="button" data-open="pw-<?= $id ?>">Ganti password</button><button class="btn <?= $on ? 'danger' : 'outline' ?> small" type="button" data-open="st-<?= $id ?>"><?= $on ? 'Nonaktifkan' : 'Aktifkan' ?></button><?php endif ?></div>
  </section>
  <?php if (!$me): ?>
  <dialog class="modal" id="pw-<?= $id ?>"><h2>Ganti password <?= e($a['name']) ?></h2><form method="post" action="/admin/tim/<?= $id ?>"><?= csrf_field() ?><input type="hidden" name="tindakan" value="password"><div class="field"><label for="new-<?= $id ?>">Password baru</label><input id="new-<?= $id ?>" name="password" type="password" data-password data-strong data-min="12" maxlength="72" autocomplete="new-password" required><p class="hint">Minimal 12 karakter, gabungan huruf, angka, atau simbol.</p></div><?php $confirm("c1-$id") ?><div class="actions"><button class="btn outline" type="button" data-close>Batal</button><button class="btn primary" type="submit">Simpan</button></div></form></dialog>
  <dialog class="modal" id="st-<?= $id ?>"><h2><?= $on ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($a['name']) ?></h2><form method="post" action="/admin/tim/<?= $id ?>"><?= csrf_field() ?><input type="hidden" name="tindakan" value="<?= $on ? 'nonaktif' : 'aktif' ?>"><?php $confirm("c2-$id") ?><div class="actions"><button class="btn outline" type="button" data-close>Batal</button><button class="btn <?= $on ? 'danger' : 'primary' ?>" type="submit"><?= $on ? 'Nonaktifkan' : 'Aktifkan' ?></button></div></form></dialog>
  <?php endif ?>
  <?php endforeach ?>
</div>
<dialog class="modal" id="add"><h2>Tambah admin</h2>
  <form method="post" action="/admin/tim" data-once="Menyimpan...">
    <?= csrf_field() ?>
    <div class="field"><label for="add-name">Nama</label><input id="add-name" name="name" type="text" minlength="2" maxlength="80" value="<?= e($old['name'] ?? '') ?>" autocomplete="off" required></div>
    <div class="field"><label for="add-email">Email</label><input id="add-email" name="email" type="email" maxlength="190" value="<?= e($old['email'] ?? '') ?>" autocomplete="off" required><p class="hint">Tidak boleh dipakai akun freelancer.</p></div>
    <div class="field"><label for="add-pass">Password</label><input id="add-pass" name="password" type="password" data-password data-strong data-min="12" maxlength="72" autocomplete="new-password" required><p class="hint">Minimal 12 karakter, gabungan huruf, angka, atau simbol.</p></div>
    <?php $confirm('c-add') ?>
    <div class="actions"><button class="btn outline" type="button" data-close>Batal</button><button class="btn primary" type="submit">Tambah</button></div>
  </form>
</dialog>
