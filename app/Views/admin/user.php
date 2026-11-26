<div class="page-head"><a class="back" href="/admin/user"><?= icon('back') ?>Freelancer</a><div class="row"><h1><?= e($row['name']) ?></h1><span class="tag<?= $row['status'] === 'blocked' ? ' fail' : ' good' ?>"><?= $row['status'] === 'blocked' ? 'Diblokir' : 'Aktif' ?></span></div><p><?= e($row['email']) ?></p></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<div class="split">
  <section class="card">
    <h2>Ringkasan</h2>
    <dl class="facts">
      <div><dt>Saldo</dt><dd><?= e(rupiah($balance)) ?></dd></div>
      <div><dt>Daftar</dt><dd><?= e(date('d/m/Y', strtotime($row['created_at']))) ?></dd></div>
      <div><dt>Masuk terakhir</dt><dd><?= $row['last_login_at'] ? e(date('d/m/Y H:i', strtotime($row['last_login_at']))) : '-' ?></dd></div>
      <div><dt>Threads</dt><dd><?= $account ? '@' . e($account['username']) : '-' ?></dd></div>
      <div><dt>Link</dt><dd><?= $link ? ($link['link_verified_at'] ? 'Terpasang' : 'Tidak terpasang') : '-' ?></dd></div>
    </dl>
    <h2 class="sub">Campaign</h2>
    <?php if (!$parts): ?><p class="hint">Belum ikut campaign.</p><?php endif ?>
    <ul class="list">
      <?php foreach ($parts as $p): ?><li><div><a class="title" href="/admin/akun/<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a><p class="hint"><?= number_format((int) $p['total_views'], 0, ',', '.') ?> views, estimasi <?= e(rupiah((int) $p['fee_total'])) ?></p></div></li><?php endforeach ?>
    </ul>
  </section>
  <section class="card">
    <h2>Tindakan</h2>
    <?php if ($row['role'] === 'admin'): ?><p class="hint">Admin tidak bisa diblokir dari sini.</p><?php else: ?>
    <form method="post" action="/admin/user/<?= (int) $row['id'] ?>" data-confirm="<?= $row['status'] === 'blocked' ? 'Buka blokir user ini?' : 'Blokir user ini? Sesi dan link bio-nya langsung mati.' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="tindakan" value="<?= $row['status'] === 'blocked' ? 'buka' : 'blokir' ?>">
      <div class="field"><label for="alasan-blokir">Alasan</label><input id="alasan-blokir" name="alasan" type="text" minlength="3" maxlength="300" autocomplete="off" required></div>
      <button class="btn <?= $row['status'] === 'blocked' ? 'outline' : 'danger' ?>" type="submit"><?= $row['status'] === 'blocked' ? 'Buka blokir' : 'Blokir user' ?></button>
    </form>
    <p class="hint">User yang diblokir langsung keluar dan link bio-nya mati. Saldo tidak dihapus.</p>
    <?php endif ?>
    <?php if ($account): ?>
    <h2 class="sub">Username Threads</h2>
    <form method="post" action="/admin/user/<?= (int) $row['id'] ?>" data-once="Memeriksa...">
      <?= csrf_field() ?>
      <input type="hidden" name="tindakan" value="username">
      <div class="field"><label for="username">Username baru</label><input id="username" name="username" type="text" maxlength="31" autocapitalize="none" spellcheck="false" autocomplete="off" required><p class="hint">Hanya diterima bila ID Threads sama.</p></div>
      <button class="btn outline" type="submit">Perbarui username</button>
    </form>
    <?php if (!$parts): ?>
    <h2 class="sub">Lepas akun Threads</h2>
    <form method="post" action="/admin/user/<?= (int) $row['id'] ?>" data-confirm="Lepas akun Threads dari user ini?">
      <?= csrf_field() ?>
      <input type="hidden" name="tindakan" value="lepas">
      <div class="field"><label for="alasan-lepas">Alasan</label><input id="alasan-lepas" name="alasan" type="text" minlength="3" maxlength="300" autocomplete="off" required></div>
      <button class="btn outline" type="submit">Lepas akun</button>
    </form>
    <?php endif ?>
    <?php endif ?>
  </section>
</div>
<section class="card gap">
  <h2>Mutasi saldo</h2>
  <?php if (!$entries): ?><p class="hint">Belum ada mutasi.</p><?php endif ?>
  <ul class="list"><?php foreach ($entries as $x): ?><li><div class="entry"><span class="bubble <?= $x['amount'] > 0 ? 'in' : 'out' ?>"><?= icon($x['amount'] > 0 ? 'in' : 'up') ?></span><div><strong class="font-medium"><?= ['fee' => 'Fee campaign', 'withdrawal' => 'Penarikan', 'refund' => 'Penarikan dikembalikan'][$x['type']] ?></strong><p class="hint"><?= e(date('d/m/Y H:i', strtotime($x['created_at']))) ?><?= $x['title'] ? ', ' . e($x['title']) : '' ?></p></div></div><strong class="num <?= $x['amount'] > 0 ? 'ok' : '' ?>"><?= $x['amount'] > 0 ? '+' : '-' ?><?= e(rupiah(abs((int) $x['amount']))) ?></strong></li><?php endforeach ?></ul>
</section>
