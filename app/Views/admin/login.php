<section class="auth">
  <h1>Masuk admin</h1>
  <p class="lead-sm">Khusus tim <?= e(setting('site_name')) ?>. Akun freelancer tidak berlaku di sini.</p>
  <?php if (!empty($errors['form'])): ?><div class="notice bad pop" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
  <form method="post" action="/admin/masuk" data-once="Memeriksa...">
    <?= csrf_field() ?>
    <?php
    (function () use ($old) { $key = 'email'; $label = 'Email admin'; $type = 'email'; $auto = 'username'; $value = $old['email'] ?? ''; $error = null; require ROOT . '/app/Views/partials/field.php'; })();
    (function () { $key = 'password'; $label = 'Password'; $type = 'password'; $auto = 'current-password'; $error = null; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <button class="btn primary large block" type="submit"><span>Masuk</span></button>
  </form>
</section>
