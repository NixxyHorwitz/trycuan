<?php
$rate = App\Models\Setting::int('home_rate');
$open = App\Models\Setting::int('register_open');
?>
<section class="hero">
  <div>
    <h1><?= e(setting('home_title')) ?></h1>
    <p class="lead"><?= e(setting('home_lead')) ?></p>
    <div class="actions">
      <a class="btn primary large" href="<?= $user ? '/dashboard' : ($open ? '/daftar' : '/masuk') ?>"><?= $user ? 'Buka beranda' : ($open ? e(setting('home_cta')) : 'Masuk') ?><?= icon('arrow') ?></a>
      <a class="btn outline large" href="#cara">Cara kerja</a>
    </div>
  </div>
  <div class="hero-card" data-rate="<?= $rate ?>">
    <div class="field"><label for="views">Total views akun</label>
      <input id="views" class="bg-white/10! border-white/20! text-white! text-lg!" type="number" inputmode="numeric" min="0" max="10000000" step="100" value="12480">
      <input id="views-range" class="min-h-8! border-0! bg-transparent! p-0! shadow-none!" type="range" min="0" max="100000" step="100" value="12480" aria-label="Geser total views"></div>
    <div><p class="hint">Estimasi fee</p><output class="amount block" id="fee" for="views"><?= e(rupiah(12 * $rate)) ?></output></div>
    <div class="stack gap-2!"><progress id="meter" max="1000" value="480" aria-label="Progres ke kelipatan berikutnya"></progress><p class="hint" id="next">520 views lagi untuk +<?= e(rupiah($rate)) ?></p></div>
    <p class="hint">Contoh <?= e(rupiah($rate)) ?> per 1.000 views. Tarif dan batas mengikuti campaign.</p>
  </div>
</section>
<section class="section" id="cara">
  <h2>Cara kerja</h2>
  <ol class="steps">
    <li><?= icon('threads') ?><h3>Verifikasi akun</h3><p>Tempel kode di bio Threads untuk membuktikan akun itu milikmu.</p></li>
    <li><?= icon('link') ?><h3>Pasang link</h3><p>Pasang link pribadi di profil sebelum mulai posting.</p></li>
    <li><?= icon('send') ?><h3>Posting utas</h3><p>Ikuti syarat campaign, lalu kirim link utas untuk diperiksa.</p></li>
    <li><?= icon('wallet') ?><h3>Terima fee</h3><p>Fee cair setelah views dan kualitas klik akun lolos pemeriksaan.</p></li>
  </ol>
  <p class="mt-5"><a href="/panduan">Baca panduan lengkap</a></p>
</section>
<?php if ($campaigns): ?>
<section class="section">
  <div class="row"><h2>Campaign yang dibuka</h2><a href="/program">Lihat semua</a></div>
  <div class="cards"><?php foreach ($campaigns as $c) require ROOT . '/app/Views/partials/program_card.php' ?></div>
</section>
<?php endif ?>
<?php if ($faq): ?>
<section class="section">
  <h2>Tanya jawab</h2>
  <div class="faq"><?php foreach ($faq as [$question, $answer]): ?><details class="card" name="faq"><summary><?= e($question) ?><?= icon('down') ?></summary><p class="text-soft"><?= e($answer) ?></p></details><?php endforeach ?></div>
</section>
<?php endif ?>
