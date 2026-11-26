<?php
use App\Services\GoogleLogin;
require ROOT . '/app/Views/partials/profile_head.php';
$has = (int) $user['has_password'] === 1;
?>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<div class="stack">
  <section class="card setting-block">
    <div><h2><?= $has ? 'Ganti password' : 'Buat password' ?></h2><p class="hint"><?= $has ? 'Perangkat lain otomatis keluar setelah password diganti.' : 'Kamu masuk lewat Google. Password memberi cara masuk cadangan.' ?></p>
      <ul class="tips"><li><?= icon('shield') ?>Jangan pakai password yang sama di situs lain.</li><li><?= icon('key') ?>Gunakan tombol Buatkan, lalu simpan di pengelola password.</li></ul></div>
    <form method="post" action="/profil/password" data-once="Menyimpan..." data-personal="<?= e(json_encode([$user['name'], $user['email']])) ?>">
      <?= csrf_field() ?>
      <?php
      if ($has) (function () use ($errors) { $key = 'current_password'; $label = 'Password saat ini'; $type = 'password'; $auto = 'current-password'; $error = $errors['current_password'] ?? null; require ROOT . '/app/Views/partials/field.php'; })();
      (function () use ($errors) { $key = 'new_password'; $label = 'Password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['new_password'] ?? null; $strong = App\Core\PasswordPolicy::MIN; $again = 'new_password_again'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
      (function () use ($errors) { $key = 'new_password_again'; $label = 'Ulangi password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['new_password_again'] ?? null; $match = 'new_password'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
      ?>
      <div class="save-bar"><button class="btn primary" type="submit"><?= $has ? 'Ganti password' : 'Buat password' ?></button></div>
    </form>
  </section>
  <?php if (GoogleLogin::enabled() || $user['google_sub']): ?>
  <section class="card setting-block">
    <div><h2>Login Google</h2><p class="hint">Masuk tanpa mengetik password.</p></div>
    <div>
      <?php if (!empty($errors['google'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['google']) ?></span></div><?php endif ?>
      <?php if (!$user['google_sub']): ?><a class="btn outline google" href="/masuk/google" data-turbo="false"><?php require ROOT . '/app/Views/partials/google_mark.php' ?>Hubungkan Google</a>
      <?php else: ?><div class="row"><span class="tag good">Terhubung</span></div>
      <?php if ($has): ?><details class="mt-2"><summary>Lepas akun Google<?= icon('down') ?></summary><form method="post" action="/profil/google"><?= csrf_field() ?><?php (function () { $key = 'konfirmasi'; $label = 'Password kamu'; $type = 'password'; $auto = 'current-password'; $error = null; require ROOT . '/app/Views/partials/field.php'; })() ?><button class="btn danger" type="submit">Lepas</button></form></details>
      <?php else: ?><p class="hint mt-2">Buat password dulu bila ingin melepas Google.</p><?php endif ?><?php endif ?>
    </div>
  </section>
  <?php endif ?>
  <section class="card setting-block">
    <div><h2>Perangkat</h2><p class="hint">Akun ini pernah dipakai masuk di <?= max(1, $devices) ?> perangkat.</p></div>
    <div><p class="mb-3 text-soft">Masuk terakhir <?= $user['last_login_at'] ? e(date('d/m/Y H:i', strtotime($user['last_login_at']))) . ' WIB' : 'belum tercatat' ?>.</p>
      <form class="bare" method="post" action="/profil/sesi" data-confirm="Keluarkan semua perangkat selain yang ini?"><?= csrf_field() ?><button class="btn outline" type="submit"><?= icon('logout') ?>Keluarkan perangkat lain</button></form></div>
  </section>
</div>
