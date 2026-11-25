<?php
require ROOT . '/app/Views/partials/head.php';
$nav = [['dashboard', '/dashboard', 'grid', 'Dashboard'], ['campaign', '/campaign', 'campaign', 'Campaign'], ['account', '/akun-threads', 'link', 'Akun'], ['profile', '/profil', 'user', 'Profil']];
if ($user['role'] === 'admin') $nav[] = ['admin', '/admin', 'shield', 'Admin'];
?>
<body class="shell">
  <aside class="sidebar">
    <a class="brand" href="/dashboard"><span class="mark"><?= icon('trend') ?></span>TryCuan</a>
    <nav class="nav" aria-label="Menu utama">
      <?php foreach ($nav as [$key, $href, $ic, $label]): ?>
        <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label ?></span></a>
      <?php endforeach ?>
    </nav>
    <form class="logout" method="post" action="/keluar">
      <?= csrf_field() ?>
      <button type="submit"><?= icon('logout') ?><span>Keluar</span></button>
    </form>
  </aside>
  <main class="content">
    <?php require ROOT . '/app/Views/partials/flash.php' ?>
    <?= $content ?>
  </main>
</body>
</html>
