<section class="auth">
  <h1>Buat password baru</h1>
  <?php if (!$valid): ?>
  <p class="lead-sm">Link tidak berlaku, sudah dipakai, atau kedaluwarsa.</p>
  <a class="btn primary large block" href="/lupa-password">Minta link baru</a>
  <?php else: ?>
  <p class="lead-sm">Semua perangkat yang sedang masuk akan dikeluarkan.</p>
  <form method="post" action="/reset-password" data-once="Menyimpan...">
    <?= csrf_field() ?>
    <?php
    (function () use ($errors, $old) { $key = 'password'; $label = 'Password baru'; $type = 'password'; $auto = 'new-password'; $error = $errors['password'] ?? null; $strong = App\Core\PasswordPolicy::MIN; $again = 'password_again'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
    (function () use ($errors, $old) { $key = 'password_again'; $label = 'Ulangi password'; $type = 'password'; $auto = 'new-password'; $error = null; $match = 'password'; $attrs = 'maxlength="72"'; require ROOT . '/app/Views/partials/field.php'; })();
    ?>
    <button class="btn primary large block" type="submit"><span>Simpan password</span></button>
  </form>
  <?php endif ?>
</section>
