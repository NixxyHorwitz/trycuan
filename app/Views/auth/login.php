<section class="auth card">
  <h1>Masuk</h1>
  <?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
  <form method="post" action="/masuk" data-once>
    <?= csrf_field() ?>
    <?php
    (function () use ($old) { $key = 'email'; $label = 'Email'; $type = 'email'; $auto = 'username'; $value = $old['email'] ?? ''; $error = null; require ROOT . '/app/Views/partials/field.php'; })();
    (function () { $key = 'password'; $label = 'Password'; $type = 'password'; $auto = 'current-password'; $error = null; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <button class="btn primary large block" type="submit">Masuk</button>
  </form>
  <p class="switch">Belum punya akun? <a href="/daftar">Daftar</a></p>
</section>
