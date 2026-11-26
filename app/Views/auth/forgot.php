<section class="auth">
  <a class="back" href="/masuk"><?= icon('back') ?>Kembali ke masuk</a>
  <h1>Lupa password</h1>
  <p class="lead-sm">Kami kirim link untuk membuat password baru. Berlaku 30 menit.</p>
  <?php if ($flash): ?><div class="notice ok pop" role="status"><?= icon('check') ?><span><?= e($flash) ?></span></div><?php endif ?>
  <?php if ($flash && App\Core\Env::get('MAIL_DRIVER') !== 'smtp' && !str_starts_with(App\Core\Env::get('APP_URL'), 'https://')): ?><div class="notice warn"><?= icon('alert') ?><span>Mode lokal: email tidak dikirim. Link ada di <code>storage/logs/mail.log</code>.</span></div><?php endif ?>
  <form method="post" action="/lupa-password" data-once="Mengirim...">
    <?= csrf_field() ?>
    <?php (function () use ($errors, $old) { $key = 'email'; $label = 'Email akun'; $type = 'email'; $auto = 'username'; $value = $old['email'] ?? ''; $error = $errors['email'] ?? null; $attrs = 'maxlength="190" placeholder="nama@gmail.com" inputmode="email" autocapitalize="none" spellcheck="false"'; require ROOT . '/app/Views/partials/field.php'; })(); ?>
    <button class="btn primary large block" type="submit"><span>Kirim link</span></button>
  </form>
</section>
