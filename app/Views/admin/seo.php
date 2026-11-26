<?php
use App\Models\Setting;
use App\Services\Seo;
$ok = count(array_filter($checks, static fn (array $c): bool => $c[0]));
$score = (int) round($ok / count($checks) * 100);
?>
<div class="page-head row"><div><h1>SEO</h1><p>Tampilan situs di mesin pencari dan media sosial.</p></div><div class="actions"><a class="btn outline" href="/sitemap.xml" target="_blank" rel="noopener"><?= icon('external') ?>sitemap.xml</a><a class="btn outline" href="/robots.txt" target="_blank" rel="noopener"><?= icon('external') ?>robots.txt</a></div></div>
<div class="settings">
  <nav class="settings-nav" aria-label="Bagian SEO">
    <a href="/admin/seo" aria-current="page"><?= icon('activity') ?>Ringkasan</a>
    <?php foreach (Setting::groups() as $key => [$name, $ic]): if (!str_starts_with($key, 'seo-')) continue; ?><a href="/admin/seo?grup=<?= e($key) ?>"><?= icon($ic) ?><?= e($name) ?></a><?php endforeach ?>
  </nav>
  <div class="stack">
    <section class="card">
      <div class="row mb-3"><h2>Kesiapan</h2><strong class="big<?= $score >= 80 ? ' ok' : ' accent' ?>"><?= $ok ?> dari <?= count($checks) ?></strong></div>
      <progress max="100" value="<?= $score ?>" aria-label="Kesiapan SEO <?= $score ?> persen"></progress>
      <ul class="list mt-4"><?php foreach ($checks as [$pass, $name, $why, $fix]): ?>
        <li><div class="entry"><span class="bubble<?= $pass ? ' in' : '' ?>"><?= icon($pass ? 'check' : 'alert') ?></span><div><strong class="font-medium"><?= e($name) ?></strong><p class="hint"><?= e($why) ?></p></div></div><div class="meta"><?php if (!$pass && $fix): ?><a class="btn outline small" href="/admin/seo?grup=<?= e($fix) ?>">Perbaiki</a><?php else: ?><span class="tag<?= $pass ? ' good' : ' wait' ?>"><?= $pass ? 'Beres' : 'Perlu dicek' ?></span><?php endif ?></div></li>
      <?php endforeach ?></ul>
    </section>
    <div class="two">
      <section class="card"><h2>Hasil Google</h2><div class="serp"><span><?= e(Seo::base()) ?></span><strong><?= e(Seo::title(setting('seo_home_title'))) ?></strong><p><?= e(setting('seo_home_description')) ?></p></div></section>
      <section class="card"><h2>Media sosial</h2><div class="share"><img src="<?= e(parse_url(Seo::image(), PHP_URL_PATH)) ?>" alt="Gambar berbagi"><div><span><?= e(strtoupper((string) parse_url(Seo::base(), PHP_URL_HOST))) ?></span><strong><?= e(Seo::title(setting('seo_home_title'))) ?></strong></div></div></section>
    </div>
    <section class="card"><h2>Yang sudah otomatis</h2><ul class="rules"><li>Judul, deskripsi, dan alamat kanonik untuk tiap halaman publik.</li><li>Open Graph dan kartu X, dibaca WhatsApp, Facebook, Instagram, Threads, LinkedIn, Telegram, Discord, dan Pinterest.</li><li>Data terstruktur: organisasi, situs, halaman, remah navigasi, dan tanya jawab.</li><li>Sitemap mengikuti campaign yang sedang dibuka. Halaman anggota dan admin tidak diindeks.</li><li>Link pendek freelancer selalu dikecualikan dari mesin pencari.</li></ul></section>
  </div>
</div>
