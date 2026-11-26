<?php
use App\Models\PayoutMethod;
use App\Models\User;
require ROOT . '/app/Views/partials/profile_head.php';
$style = isset(User::AVATARS[$user['avatar_style']]) ? $user['avatar_style'] : 'thumbs';
?>
<div class="split">
  <form class="card" method="post" action="/profil" data-once="Menyimpan...">
    <?= csrf_field() ?>
    <div class="mb-1"><h2>Data diri</h2><p class="hint">Nama dipakai untuk menyapa dan dilihat admin.</p></div>
    <?php (function () use ($errors, $old, $user) { $key = 'name'; $label = 'Nama'; $type = 'text'; $auto = 'name'; $value = $old['name'] ?? $user['name']; $error = $errors['name'] ?? null; $attrs = 'minlength="2" maxlength="80"'; require ROOT . '/app/Views/partials/field.php'; })() ?>
    <div class="field"><label for="email-view">Email</label><input id="email-view" type="email" value="<?= e($user['email']) ?>" readonly><p class="hint">Dipakai untuk masuk dan tidak bisa diubah sendiri.</p></div>
    <fieldset class="avatar-pick" data-avatars data-url="https://api.dicebear.com/10.x/">
      <div class="row"><legend>Avatar</legend><button class="text-btn" type="button" data-shuffle><?= icon('refresh') ?>Acak</button></div>
      <input type="hidden" name="avatar_seed" value="<?= e($user['avatar_seed']) ?>">
      <div class="avatar-grid"><?php foreach (User::AVATARS as $key => $label): ?><label><input class="sr" type="radio" name="avatar_style" value="<?= e($key) ?>"<?= $key === $style ? ' checked' : '' ?>><img src="<?= e(avatar_url($key, $user['avatar_seed'])) ?>" alt="" width="64" height="64" referrerpolicy="no-referrer"><span><?= e($label) ?></span></label><?php endforeach ?></div>
    </fieldset>
    <div class="save-bar"><button class="btn primary" type="submit">Simpan perubahan</button></div>
  </form>
  <div class="stack">
    <section class="card"><h2>Ringkasan</h2><ul class="pairs"><li><span class="text-soft">Saldo</span><strong><?= e(rupiah($balance)) ?></strong></li><li><span class="text-soft">Akun Threads</span><strong><?= $account ? '@' . e($account['username']) : '<a href="/akun-threads">Hubungkan</a>' ?></strong></li><li><span class="text-soft">Rekening</span><strong><?= $method ? e(PayoutMethod::PROVIDERS[$method['provider']] ?? $method['provider']) : '<a href="/saldo">Tambahkan</a>' ?></strong></li><li><span class="text-soft">Login Google</span><strong><?= $user['google_sub'] ? 'Terhubung' : 'Belum' ?></strong></li></ul></section>
    <section class="card"><h2>Pintasan</h2><ul class="shortcuts"><li><a href="/profil/keamanan"><?= icon('lock') ?><span>Password dan keamanan</span><?= icon('chevron') ?></a></li><li><a href="/saldo"><?= icon('wallet') ?><span>Saldo dan rekening</span><?= icon('chevron') ?></a></li><li><a href="/pengumuman"><?= icon('bell') ?><span>Pengumuman</span><?= icon('chevron') ?></a></li></ul></section>
    <form class="bare" method="post" action="/keluar" data-turbo="false"><?= csrf_field() ?><button class="btn outline w-full" type="submit"><?= icon('logout') ?><span>Keluar</span></button></form>
  </div>
</div>
