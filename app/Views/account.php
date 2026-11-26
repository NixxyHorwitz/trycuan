<div class="page-head"><h1>Akun Threads</h1><p>Hubungkan satu akun Threads milikmu.</p></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad narrow" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>

<?php if ($account): ?>
  <section class="card narrow">
    <div class="row"><div class="entry"><?= avatar($user) ?><h2>@<?= e($account['username']) ?></h2></div><span class="tag good">Terverifikasi</span></div>
    <p class="hint mt-3">Terhubung sejak <?= e(date('d/m/Y H:i', strtotime($account['verified_at']))) ?> WIB. Kepemilikan terikat ke ID akun, jadi tetap berlaku jika username diganti. Biarkan akun tetap publik.</p>
  </section>
  <?php require ROOT . '/app/Views/partials/link_card.php' ?>

<?php elseif ($pending): ?>
  <section class="card narrow">
    <div class="card-head">
      <h2 class="handle">@<?= e($pending['username']) ?></h2>
      <span class="tag wait">Menunggu verifikasi</span>
    </div>
    <ol class="checklist">
      <li><span class="dot">1</span><div><strong>Salin kode</strong>
        <div class="code"><output id="code"><?= e($pending['code']) ?></output><button class="btn outline" type="button" data-copy="code"><?= icon('copy') ?>Salin</button></div>
        <p class="hint">Berlaku sampai <?= e(date('H:i', (int) $pending['expires_at'])) ?> WIB.</p></div></li>
      <li><span class="dot">2</span><div><strong>Tempel di bio Threads</strong><p>Buka profil, pilih Edit profil, tempel kode di bagian bio, lalu simpan. Isi bio lain tidak perlu dihapus.</p></div></li>
      <li><span class="dot">3</span><div><strong>Verifikasi</strong><p>Pastikan akun publik. Pengecekan berlangsung beberapa detik.</p></div></li>
    </ol>
    <div class="actions gap">
      <form method="post" action="/akun-threads/verifikasi" data-once="Memeriksa bio...">
        <?= csrf_field() ?>
        <button class="btn primary" type="submit">Verifikasi sekarang</button>
      </form>
      <form method="post" action="/akun-threads/batal" data-once>
        <?= csrf_field() ?>
        <button class="btn ghost" type="submit">Ganti username</button>
      </form>
    </div>
  </section>

<?php else: ?>
  <section class="card narrow">
    <?php if ($expired): ?><div class="notice bad" role="status"><?= icon('alert') ?><span>Kode sebelumnya kedaluwarsa. Buat kode baru.</span></div><?php endif ?>
    <form method="post" action="/akun-threads" data-once>
      <?= csrf_field() ?>
      <?php (function () use ($errors, $old) { $key = 'username'; $label = 'Username Threads'; $type = 'text'; $auto = 'off'; $value = $old['username']; $error = $errors['username'] ?? null; $hint = 'Tanpa tautan, misalnya nama.kamu'; $attrs = 'maxlength="40" autocapitalize="none" spellcheck="false" placeholder="nama.kamu" data-pattern="^@?[A-Za-z0-9._]{1,30}$" data-pattern-text="Hanya huruf, angka, titik, dan garis bawah."'; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <button class="btn primary" type="submit">Buat kode</button>
    </form>
  </section>
<?php endif ?>
