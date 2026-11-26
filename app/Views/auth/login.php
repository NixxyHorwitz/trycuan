<section class="auth">
  <nav class="auth-tabs" aria-label="Masuk atau daftar"><a href="/masuk" aria-current="page">Masuk</a><?php if (App\Models\Setting::int('register_open')): ?><a href="/daftar">Daftar</a><?php endif ?></nav>
  <h1>Selamat datang lagi</h1>
  <p class="lead-sm">Masuk untuk melihat views dan saldo kamu.</p>
  <?php if ($flash): ?><div class="notice ok pop" role="status"><?= icon('check') ?><span><?= e($flash) ?></span></div><?php endif ?>
  <?php if (!empty($errors['form'])): ?><div class="notice bad pop" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?> <a href="/lupa-password">Atur ulang password</a></span></div><?php endif ?>
  <?php if (!empty($errors['google'])): ?><div class="notice bad pop" role="alert"><?= icon('alert') ?><span><?= e($errors['google']) ?></span></div><?php endif ?>
  <?php $googleText = 'Masuk dengan Google'; require ROOT . '/app/Views/partials/google_button.php' ?>
  <form method="post" action="/masuk" data-once="Memeriksa...">
    <?= csrf_field() ?>
    <?php
    (function () use ($errors, $old) { $key = 'email'; $label = 'Email'; $type = 'email'; $auto = 'username'; $value = $old['email'] ?? ''; $error = null; $attrs = 'placeholder="nama@gmail.com" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="190"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'password'; $label = 'Password'; $type = 'password'; $auto = 'current-password'; $error = null; $attrs = 'placeholder="Password kamu"'; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <div class="row text-[13px]"><label class="check items-center"><input type="checkbox" name="remember" value="1"<?= !empty($old['remember']) ? ' checked' : '' ?>>Tetap masuk 30 hari</label><a href="/lupa-password">Lupa password?</a></div>
    <button class="btn primary large block" type="submit"><span>Masuk</span></button>
  </form>
</section>
