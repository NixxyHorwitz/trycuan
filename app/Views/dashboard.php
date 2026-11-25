<div class="page-head"><div><h1>Halo, <?= e($user['name']) ?></h1><p>Ringkasan akun dan langkah kamu.</p></div></div>
<div class="stats">
  <div class="card stat"><span>Total views</span><strong>0</strong></div>
  <div class="card stat"><span>Klik valid</span><strong><?= number_format($clicks, 0, ',', '.') ?></strong></div>
  <div class="card stat"><span>Estimasi fee</span><strong>Rp0</strong></div>
  <div class="card stat"><span>Saldo</span><strong>Rp0</strong></div>
</div>
<section class="card">
  <h2>Mulai dari sini</h2>
  <ol class="checklist">
    <li class="done"><span class="dot"><?= icon('check') ?></span><div><strong>Buat akun TryCuan</strong><p>Selesai</p></div></li>
    <?php if ($account): ?>
    <li class="done"><span class="dot"><?= icon('check') ?></span><div><strong>Verifikasi akun Threads</strong><p>@<?= e($account['username']) ?></p></div></li>
    <?php else: ?>
    <li><span class="dot">2</span><div><strong>Verifikasi akun Threads</strong><p>Tempel kode di bio untuk membuktikan kepemilikan.</p></div><a class="btn primary step" href="/akun-threads">Verifikasi</a></li>
    <?php endif ?>
    <?php if ($campaign): ?>
    <li class="done"><span class="dot"><?= icon('check') ?></span><div><strong>Pilih campaign</strong><p><?= e($campaign['title']) ?></p></div></li>
    <?php else: ?>
    <li><span class="dot">3</span><div><strong>Pilih campaign</strong><p>Baca brief dan syaratnya, lalu ikut.</p></div><?php if ($account): ?><a class="btn primary step" href="/campaign">Lihat campaign</a><?php endif ?></li>
    <?php endif ?>
    <?php if ($linked): ?>
    <li class="done"><span class="dot"><?= icon('check') ?></span><div><strong>Pasang link di bio</strong><p>Terpasang</p></div></li>
    <?php else: ?>
    <li><span class="dot">4</span><div><strong>Pasang link di bio</strong><p>Link harus terdeteksi sebelum kamu posting.</p></div><?php if ($account): ?><a class="btn <?= $campaign ? 'primary' : 'outline' ?> step" href="/akun-threads#link">Pasang link</a><?php endif ?></li>
    <?php endif ?>
    <li<?= $posts ? ' class="done"' : '' ?>><span class="dot"><?= $posts ? icon('check') : '5' ?></span><div><strong>Kirim URL utas</strong><p><?= $posts ? (int) $posts . ' utas terkirim' : 'Views utas yang disetujui masuk ke total akun.' ?></p></div><?php if ($campaign): ?><a class="btn <?= $posts || !$linked ? 'outline' : 'primary' ?> step" href="/campaign/<?= (int) $campaign['id'] ?>">Buka campaign</a><?php endif ?></li>
  </ol>
</section>
