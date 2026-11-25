<div class="page-head"><div><h1>Profil</h1><p><?= e($user['email']) ?></p></div></div>
<div class="two">
  <section class="card">
    <h2>Data diri</h2>
    <form method="post" action="/profil" data-once>
      <?= csrf_field() ?>
      <?php (function () use ($errors, $old, $user) { $key = 'name'; $label = 'Nama'; $type = 'text'; $auto = 'name'; $value = $old['name'] ?? $user['name']; $error = $errors['name'] ?? null; $attrs = 'minlength="2" maxlength="80"'; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <button class="btn primary" type="submit">Simpan</button>
    </form>
  </section>
  <section class="card">
    <h2>Ganti password</h2>
    <form method="post" action="/profil/password" data-once>
      <?= csrf_field() ?>
      <?php
      (function () use ($errors) { $key = 'current_password'; $label = 'Password saat ini'; $type = 'password'; $auto = 'current-password'; $error = $errors['current_password'] ?? null; require ROOT . '/app/Views/partials/field.php'; })();
      (function () use ($errors) { $key = 'new_password'; $label = 'Password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['new_password'] ?? null; $hint = 'Minimal 8 karakter.'; $attrs = 'minlength="8" maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
      ?>
      <button class="btn primary" type="submit">Ganti password</button>
    </form>
  </section>
</div>
<form class="only-mobile gap" method="post" action="/keluar" data-once><?= csrf_field() ?><button class="btn outline" type="submit"><?= icon('logout') ?><span>Keluar</span></button></form>
