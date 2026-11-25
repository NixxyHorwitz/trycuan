<section class="auth card">
  <h1>Buat akun</h1>
  <?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
  <form method="post" action="/daftar" data-once>
    <?= csrf_field() ?>
    <?php
    (function () use ($errors, $old) { $key = 'name'; $label = 'Nama'; $type = 'text'; $auto = 'name'; $value = $old['name'] ?? ''; $error = $errors['name'] ?? null; $attrs = 'minlength="2" maxlength="80"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'email'; $label = 'Email'; $type = 'email'; $auto = 'email'; $value = $old['email'] ?? ''; $error = $errors['email'] ?? null; $attrs = 'maxlength="190"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors) { $key = 'password'; $label = 'Password'; $type = 'password'; $auto = 'new-password'; $error = $errors['password'] ?? null; $hint = 'Minimal 8 karakter.'; $attrs = 'minlength="8" maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <label class="check"><input type="checkbox" name="terms" value="1" required<?= !empty($errors['terms']) ? ' aria-invalid="true" aria-describedby="terms-error"' : '' ?>><span>Saya hanya akan memakai akun Threads milik sendiri dan tidak membeli views atau klik.</span></label>
    <?php if (!empty($errors['terms'])): ?><p class="error" id="terms-error"><?= e($errors['terms']) ?></p><?php endif ?>
    <button class="btn primary large block" type="submit">Daftar</button>
  </form>
  <p class="switch">Sudah punya akun? <a href="/masuk">Masuk</a></p>
</section>
