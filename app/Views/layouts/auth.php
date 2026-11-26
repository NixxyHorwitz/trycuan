<?php $noCache = true; require ROOT . '/app/Views/partials/head.php'; $isAdmin = App\Core\Area::isAdmin(); ?>
<body>
  <div class="auth-page">
    <aside class="auth-side">
      <a class="brand" href="<?= $isAdmin ? '/admin' : '/' ?>"><?= brand($isAdmin ? 'Admin' : '') ?></a>
      <div>
        <?php if ($isAdmin): ?>
        <h2>Panel pengelola campaign</h2><p>Tinjau utas, periksa kualitas klik, dan proses pencairan dari satu tempat.</p>
        <?php else: ?>
        <h2><?= e(setting('home_title')) ?></h2><p><?= e(setting('home_lead')) ?></p>
        <ul class="auth-points"><li><?= icon('eye') ?>Views dihitung per akun, bukan per utas</li><li><?= icon('shield') ?>Nomor rekening disimpan terenkripsi</li><li><?= icon('wallet') ?>Tarik saldo ke bank atau e-wallet</li></ul>
        <?php endif ?>
      </div>
    </aside>
    <main class="auth-main">
      <div class="auth-top">
        <a class="brand lg:hidden!" href="<?= $isAdmin ? '/admin' : '/' ?>"><?= brand($isAdmin ? 'Admin' : '') ?></a>
        <?php require ROOT . '/app/Views/partials/theme.php' ?>
      </div>
      <div class="auth-body"><?= $content ?></div>
    </main>
  </div>
</body>
</html>
