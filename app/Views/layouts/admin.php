<?php
require ROOT . '/app/Views/partials/head.php';
$todo = ['insight' => App\Models\ViewReport::pendingCount(), 'reviews' => App\Models\Submission::pendingCount(), 'withdrawals' => App\Models\Withdrawal::pendingCount()];
$menu = [
    '' => [['overview', '/admin', 'grid', 'Ringkasan']],
    'Operasional' => [['reviews', '/admin/utas', 'file', 'Review utas'], ['insight', '/admin/insight', 'eye', 'Setoran views'], ['fees', '/admin/fee', 'coins', 'Pencairan fee'], ['withdrawals', '/admin/penarikan', 'bank', 'Penarikan']],
    'Kelola' => [['campaign', '/admin/campaign', 'campaign', 'Campaign'], ['users', '/admin/user', 'users', 'Freelancer'], ['announce', '/admin/pengumuman', 'bell', 'Pengumuman']],
    'Pertumbuhan' => [['seo', '/admin/seo', 'search', 'SEO'], ['traffic', '/admin/trafik', 'chart', 'Trafik']],
    'Pengawasan' => [['clicks', '/admin/klik', 'click', 'Log klik'], ['scraper', '/admin/scraper', 'activity', 'Scraper'], ['audit', '/admin/audit', 'history', 'Audit']],
    'Sistem' => [['team', '/admin/tim', 'shield', 'Tim admin'], ['settings', '/admin/pengaturan', 'settings', 'Pengaturan']],
];
$side = static function () use ($menu, $active, $todo): void {
    foreach ($menu as $group => $items) {
        if ($group !== '') echo '<p>' . e($group) . '</p>';
        foreach ($items as [$key, $href, $ic, $label]) {
            echo '<a href="' . $href . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span>' . (!empty($todo[$key]) ? '<b>' . (int) $todo[$key] . '</b>' : '') . '</a>';
        }
    }
};
?>
<body>
  <div class="shell">
    <aside class="sidebar">
      <a class="brand" href="/admin"><?= brand('Admin') ?></a>
      <nav class="side-nav" aria-label="Menu admin"><?php $side() ?></nav>
      <form class="side-foot bare" method="post" action="/admin/keluar" data-turbo="false"><?= csrf_field() ?><button class="side-link" type="submit"><?= icon('logout') ?><span>Keluar</span></button></form>
    </aside>
    <div>
      <header class="appbar">
        <a class="brand" href="/admin"><?= brand('Admin') ?></a>
        <button class="search-btn" type="button" data-open="palette"><?= icon('search') ?>Cari halaman, freelancer, campaign<kbd>Ctrl K</kbd></button>
        <span class="spacer hidden lg:block"></span>
        <button class="icon-btn sm:hidden!" type="button" data-open="palette" aria-label="Cari"><?= icon('search') ?></button>
        <?php require ROOT . '/app/Views/partials/theme.php' ?>
        <?php require ROOT . '/app/Views/partials/user_menu.php' ?>
      </header>
      <main class="content full">
        <?php require ROOT . '/app/Views/partials/flash.php' ?>
        <?= $content ?>
      </main>
    </div>
    <nav class="dock" aria-label="Menu bawah">
      <?php foreach ([['overview', '/admin', 'grid', 'Ringkasan'], ['reviews', '/admin/utas', 'file', 'Utas'], ['fees', '/admin/fee', 'coins', 'Fee'], ['withdrawals', '/admin/penarikan', 'bank', 'Penarikan']] as [$key, $href, $ic, $label]): ?>
      <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= $label ?></span><?php if (!empty($todo[$key])): ?><b><?= (int) $todo[$key] ?></b><?php endif ?></a>
      <?php endforeach ?>
      <button type="button" data-open="drawer"><?= icon('menu') ?><span>Menu</span></button>
    </nav>
  </div>
  <dialog class="drawer" id="drawer" aria-label="Menu admin">
    <div class="row"><span class="brand"><?= brand('Admin') ?></span><button class="icon-btn" type="button" data-close aria-label="Tutup menu"><?= icon('close') ?></button></div>
    <nav class="side-nav" aria-label="Semua menu"><?php $side() ?></nav>
    <form class="side-foot bare" method="post" action="/admin/keluar" data-turbo="false"><?= csrf_field() ?><button class="side-link" type="submit"><?= icon('logout') ?><span>Keluar</span></button></form>
  </dialog>
  <dialog class="modal palette" id="palette" aria-label="Pencarian cepat">
    <input id="palette-input" type="search" placeholder="Cari halaman, freelancer, campaign" autocomplete="off" aria-label="Kata kunci" maxlength="60">
    <ul id="palette-list"></ul>
    <p id="palette-none" hidden>Tidak ada yang cocok.</p>
  </dialog>
</body>
</html>
