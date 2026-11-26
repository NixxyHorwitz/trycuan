<?php
require ROOT . '/app/Views/partials/head.php';
$isAdmin = App\Core\Area::isAdmin();
$links = $isAdmin ? [] : array_filter(['/program' => App\Models\Setting::int('public_campaigns') ? 'Campaign' : null, '/panduan' => 'Panduan']);
?>
<body>
  <header class="topbar"><div>
    <a class="brand" href="<?= $isAdmin ? '/admin' : '/' ?>"><?= brand() ?></a>
    <nav class="top-links" aria-label="Utama"><?php foreach ($links as $href => $label): ?><a href="<?= $href ?>"><?= $label ?></a><?php endforeach ?></nav>
    <nav aria-label="Akun">
      <?php require ROOT . '/app/Views/partials/theme.php' ?>
      <?php if ($user): ?>
        <a class="btn primary" href="<?= App\Core\Area::home() ?>"><?= $isAdmin ? 'Panel admin' : 'Beranda' ?></a>
      <?php elseif (!$isAdmin): ?>
        <a class="btn ghost" href="/masuk">Masuk</a>
        <?php if (App\Models\Setting::int('register_open')): ?><a class="btn primary" href="/daftar">Daftar</a><?php endif ?>
      <?php endif ?>
    </nav>
  </div></header>
  <main class="public"><?= $content ?></main>
  <footer class="foot">
    <div><strong><?= e(setting('site_name')) ?></strong><?php if (setting('footer_text') !== ''): ?><p><?= e(setting('footer_text')) ?></p><?php endif ?></div>
    <nav aria-label="Kaki halaman"><?php foreach ($links + ['/privasi' => 'Privasi'] as $href => $label): ?><a href="<?= $href ?>"><?= $label ?></a><?php endforeach ?>
    <?php if (setting('contact_email') !== ''): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Email</a><?php endif ?>
    <?php if (setting('contact_url') !== ''): ?><a href="<?= e(setting('contact_url')) ?>" target="_blank" rel="noopener noreferrer">Bantuan</a><?php endif ?>
    <?php foreach (App\Services\Seo::SOCIALS as $key => $label): if (setting("social_$key") === '') continue; ?><a href="<?= e(setting("social_$key")) ?>" target="_blank" rel="me noopener noreferrer"><?= e($label) ?></a><?php endforeach ?></nav>
  </footer>
</body>
</html>
