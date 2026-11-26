<section class="auth">
  <nav class="auth-tabs" aria-label="Masuk atau daftar"><a href="/masuk">Masuk</a><a href="/daftar" aria-current="page">Daftar</a></nav>
  <h1>Buat akun</h1>
  <p class="lead-sm">Gratis. Setelah ini kamu hubungkan akun Threads.</p>
  <?php if (!empty($errors['form'])): ?><div class="notice bad pop" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
  <?php if (!App\Models\Setting::int('register_open')): ?><section class="empty"><?= icon('lock') ?><h2>Pendaftaran ditutup</h2><p>Saat ini belum menerima akun baru.</p><a class="btn outline" href="/masuk">Masuk</a></section></section><?php return; endif ?>
  <?php $googleText = 'Daftar dengan Google'; require ROOT . '/app/Views/partials/google_button.php' ?>
  <?php if (App\Services\GoogleLogin::enabled()): ?><p class="hint mb-4 text-center">Dengan mendaftar, kamu menyetujui syarat di bawah dan <a href="/privasi">Privasi</a>.</p><?php endif ?>
  <form method="post" action="/daftar" data-once="Membuat akun...">
    <?= csrf_field() ?>
    <?php
    (function () use ($errors, $old) { $key = 'name'; $label = 'Nama'; $type = 'text'; $auto = 'name'; $value = $old['name'] ?? ''; $error = $errors['name'] ?? null; $attrs = 'minlength="2" maxlength="80" placeholder="Nama lengkap" autocapitalize="words"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'email'; $label = 'Email'; $type = 'email'; $auto = 'email'; $value = $old['email'] ?? ''; $error = $errors['email'] ?? null; $attrs = 'maxlength="190" placeholder="nama@gmail.com" inputmode="email" autocapitalize="none" spellcheck="false"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'password'; $label = 'Password'; $type = 'password'; $auto = 'new-password'; $error = $errors['password'] ?? null; $strong = App\Core\PasswordPolicy::MIN; $again = 'password_again'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'password_again'; $label = 'Ulangi password'; $type = 'password'; $auto = 'new-password'; $error = $errors['password_again'] ?? null; $match = 'password'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <div class="check-field"><label class="check"><input type="checkbox" name="terms" value="1" required data-required="Setujui dulu untuk melanjutkan."<?= !empty($errors['terms']) ? ' aria-invalid="true"' : '' ?>><span>Saya hanya akan memakai akun Threads milik sendiri dan tidak membeli views atau klik. Saya sudah membaca <a href="/privasi">Privasi</a>.</span></label>
    <?php if (!empty($errors['terms'])): ?><p class="error"><?= e($errors['terms']) ?></p><?php endif ?></div>
    <button class="btn primary large block" type="submit"><span>Buat akun</span></button>
  </form>
</section>
