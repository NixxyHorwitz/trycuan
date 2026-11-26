<?php
require ROOT . '/app/Views/partials/head.php';
$nav = [['dashboard', '/dashboard', 'home', 'Beranda'], ['campaign', '/campaign', 'campaign', 'Campaign'], ['posts', '/utas', 'file', 'Utas'], ['account', '/akun-threads', 'threads', 'Akun'], ['balance', '/saldo', 'wallet', 'Saldo']];
$unread = $active === 'notice' ? 0 : App\Models\Announcement::unread($user);
?>
<body>
  <div class="shell">
    <aside class="sidebar">
      <a class="brand" href="/dashboard"><?= brand() ?></a>
      <nav class="side-nav" aria-label="Menu utama">
        <?php foreach ($nav as [$key, $href, $ic, $label]): ?>
        <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label === 'Akun' ? 'Akun Threads' : $label ?></span></a>
        <?php endforeach ?>
      </nav>
      <form class="side-foot bare" method="post" action="/keluar" data-turbo="false"><?= csrf_field() ?><button class="side-link" type="submit"><?= icon('logout') ?><span>Keluar</span></button></form>
    </aside>
    <div>
      <header class="appbar">
        <a class="brand" href="/dashboard"><?= brand() ?></a>
        <span class="spacer hidden lg:block"></span>
        <?php require ROOT . '/app/Views/partials/theme.php' ?>
        <a class="icon-btn" href="/pengumuman" aria-label="Pengumuman<?= $unread ? ", $unread belum dibaca" : '' ?>"><?= icon('bell') ?><?php if ($unread): ?><span class="count"><?= min(99, $unread) ?></span><?php endif ?></a>
        <?php require ROOT . '/app/Views/partials/user_menu.php' ?>
      </header>
      <main class="content">
        <?php require ROOT . '/app/Views/partials/flash.php' ?>
        <?= $content ?>
      </main>
    </div>
    <nav class="dock" aria-label="Menu bawah">
      <?php foreach ($nav as [$key, $href, $ic, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label ?></span></a>
      <?php endforeach ?>
    </nav>
  </div>
</body>
</html>
