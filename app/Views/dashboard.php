<?php
$part = $card['part'] ?? null;
$ready = $card['credit']['ready'] ?? 0;
$steps = !$posts || !$campaign || !$linked;
?>
<div class="page-head"><h1>Halo, <?= e(explode(' ', $user['name'])[0]) ?></h1><p><?= $campaign ? e($campaign['title']) : 'Belum ada campaign yang diikuti' ?></p></div>
<?php if ($notice): ?><a class="notice <?= $notice['level'] === 'penting' ? 'warn' : 'ok' ?>" href="/pengumuman"><?= icon('bell') ?><span><strong><?= e($notice['title']) ?></strong></span></a><?php endif ?>
<section class="hero-card">
  <div class="row">
    <div><p class="hint">Bisa ditarik</p><p class="amount"><?= e(rupiah($balance)) ?></p></div>
    <div class="actions"><a class="btn primary" href="/saldo"><?= icon('wallet') ?>Tarik saldo</a><a class="btn outline" href="/campaign">Cari campaign</a></div>
  </div>
  <div class="hero-stats">
    <div><span>Estimasi fee</span><strong><?= e(rupiah((int) ($part['fee_total'] ?? 0))) ?></strong></div>
    <div><span>Diperiksa</span><strong><?= e(rupiah($ready)) ?></strong></div>
    <div><span>Utas</span><strong><?= (int) $posts ?></strong></div>
  </div>
</section>
<?php if ($steps): ?>
<section class="card gap">
  <?php $done = 1 + (int) (bool) $account + (int) (bool) $campaign + (int) $linked + (int) (bool) $posts; ?>
  <div class="card-head"><h2>Mulai dari sini</h2><span class="tag on"><?= $done ?> dari 5</span></div>
  <progress class="mb-4" max="5" value="<?= $done ?>" aria-label="Langkah selesai"></progress>
  <ol class="checklist">
    <li class="done"><span class="dot"><?= icon('check') ?></span><div><strong>Buat akun <?= e(setting('site_name')) ?></strong></div></li>
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
<?php endif ?>
<?php if (count($running) > 1): ?><div class="chips gap mb-0!"><?php foreach ($running as $c): ?><a class="chip" href="/dashboard?campaign=<?= (int) $c['id'] ?>" data-turbo-action="replace"<?= $campaign && (int) $c['id'] === (int) $campaign['id'] ? ' aria-current="true"' : '' ?>><?= e($c['title']) ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($card): ?><div class="gap"><?php require ROOT . '/app/Views/partials/account_card.php' ?></div><?php endif ?>
