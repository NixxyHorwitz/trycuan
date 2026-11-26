<section class="profile-head"><div class="cover"></div><div class="who"><?= avatar($user, 'xl') ?><div><h1><?= e($user['name']) ?></h1><p><?= e($user['email']) ?></p></div><span class="tag on">Admin</span></div></section>
<form class="stack" method="post" action="/admin/akun-saya" data-once="Menyimpan..." data-personal="<?= e(json_encode([$user['name'], $user['email']])) ?>">
  <?= csrf_field() ?>
  <section class="card setting-block"><div><h2>Data diri</h2><p class="hint">Nama tampil di audit dan tim admin.</p></div><div>
  <?php (function () use ($errors, $old, $user) { $key = 'name'; $label = 'Nama'; $type = 'text'; $auto = 'name'; $value = $old['name'] ?? $user['name']; $error = $errors['name'] ?? null; $attrs = 'maxlength="80"'; require ROOT . '/app/Views/partials/field.php'; })() ?>
  </div></section>
  <section class="card setting-block"><div><h2>Password baru</h2><p class="hint">Kosongkan bila tidak diganti. Minimal 12 karakter.</p></div><div class="stack">
  <?php
  (function () use ($errors) { $key = 'password'; $label = 'Password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['password'] ?? null; $strong = App\Core\PasswordPolicy::ADMIN_MIN; $again = 'password_again'; $optional = true; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
  (function () use ($errors) { $key = 'password_again'; $label = 'Ulangi password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['password_again'] ?? null; $match = 'password'; $optional = true; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
  (function () use ($errors) { $key = 'konfirmasi'; $label = 'Password saat ini'; $type = 'password'; $auto = 'current-password'; $error = $errors['konfirmasi'] ?? null; $hint = 'Hanya untuk mengganti password.'; $optional = true; $attrs = ''; require ROOT . '/app/Views/partials/field.php'; })();
  ?>
  </div></section>
  <div class="save-bar"><button class="btn primary" type="submit">Simpan perubahan</button></div>
</form>
