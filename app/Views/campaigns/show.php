<?php
use App\Models\Campaign;
$id = (int) $campaign['id'];
$rate = (int) ($part['rate_snapshot'] ?? $campaign['rate_per_1k']);
$cap = (int) ($part['cap_snapshot'] ?? $campaign['cap_per_account']);
?>
<div class="page-head">
  <a class="back" href="/campaign">Semua campaign</a>
  <div class="row"><h1><?= e($campaign['title']) ?></h1><span class="tag<?= $state === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($state)) ?></span></div>
</div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>

<dl class="facts card">
  <div><dt>Per 1.000 views</dt><dd><?= e(rupiah($rate)) ?></dd></div>
  <div><dt>Maksimum per akun</dt><dd><?= e(rupiah($cap)) ?></dd></div>
  <div><dt>Mulai</dt><dd><?= e(date('d/m/Y H:i', strtotime($campaign['starts_at']))) ?></dd></div>
  <div><dt>Selesai</dt><dd><?= e(date('d/m/Y H:i', strtotime($campaign['ends_at']))) ?> WIB</dd></div>
</dl>

<div class="two">
  <section class="card">
    <h2>Brief</h2>
    <p class="prose"><?= nl2br(e($campaign['brief'])) ?></p>
    <h2 class="sub">Syarat utas</h2>
    <ul class="rules">
      <li>Link TryCuan terpasang di profil sebelum posting dan selama campaign.</li>
      <li>Post utama dari akun terverifikasi, bukan balasan, dan diposting dalam periode campaign.</li>
      <?php if ($rules['min_length']): ?><li>Teks minimal <?= (int) $rules['min_length'] ?> karakter.</li><?php endif ?>
      <?php if ($rules['required']): ?><li>Wajib memuat: <?php foreach ($rules['required'] as $term): ?><code><?= e($term) ?></code> <?php endforeach ?></li><?php endif ?>
      <?php if ($rules['banned']): ?><li>Dilarang memuat: <?php foreach ($rules['banned'] as $term): ?><code><?= e($term) ?></code> <?php endforeach ?></li><?php endif ?>
    </ul>
  </section>

  <section class="card">
  <?php if (!$account): ?>
    <h2>Verifikasi akun dulu</h2>
    <p class="hint">Campaign hanya bisa diikuti dengan akun Threads terverifikasi.</p>
    <a class="btn primary gap" href="/akun-threads">Verifikasi akun</a>
  <?php elseif (!$part): ?>
    <h2>Ikut campaign</h2>
    <?php if ($busy): ?>
    <p class="hint">@<?= e($account['username']) ?> masih mengikuti <a href="/campaign/<?= (int) $busy['id'] ?>"><?= e($busy['title']) ?></a>.</p>
    <?php elseif ($state !== 'open'): ?>
    <p class="hint">Campaign dibuka <?= e(date('d/m/Y H:i', strtotime($campaign['starts_at']))) ?> WIB.</p>
    <?php else: ?>
    <p class="hint">@<?= e($account['username']) ?> terikat ke campaign ini sampai selesai. Kamu masih bisa keluar selama belum mengirim utas.</p>
    <form class="gap" method="post" action="/campaign/<?= $id ?>/ikut" data-once><?= csrf_field() ?><button class="btn primary" type="submit">Ikut campaign</button></form>
    <?php endif ?>
  <?php else: ?>
    <h2>Kirim utas</h2>
    <?php if ($state === 'open' && !$linked): ?>
    <?php if (!empty($errors['url'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['url']) ?></span></div><?php endif ?>
    <p class="hint">Pasang link TryCuan di profil Threads dulu. Utas yang diposting sebelum link terdeteksi akan ditolak.</p>
    <a class="btn primary gap" href="/akun-threads#link">Pasang link</a>
    <?php elseif ($state === 'open'): ?>
    <form method="post" action="/campaign/<?= $id ?>/utas" data-once="Memeriksa utas...">
      <?= csrf_field() ?>
      <?php (function () use ($errors, $old) { $key = 'url'; $label = 'URL utas'; $type = 'url'; $value = $old['url']; $error = $errors['url'] ?? null; $hint = 'Salin tautan dari post di Threads.'; $attrs = 'maxlength="300" placeholder="https://www.threads.com/@nama/post/..."'; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <button class="btn primary" type="submit">Kirim utas</button>
    </form>
    <?php else: ?><p class="hint">Campaign sedang tidak menerima utas.</p><?php endif ?>
    <?php if (!$posts): ?>
    <form class="gap" method="post" action="/campaign/<?= $id ?>/batal" data-once><?= csrf_field() ?><button class="btn outline" type="submit">Keluar dari campaign</button></form>
    <?php endif ?>
  <?php endif ?>
  </section>
</div>

<?php if ($part): ?>
<section class="card gap">
  <h2>Utas kamu (<?= count($posts) ?>)</h2>
  <?php if (!$posts): ?><p class="hint">Belum ada utas. Posting sesuai brief, lalu kirim URL-nya.</p><?php endif ?>
  <ul class="posts">
    <?php foreach ($posts as $p): ?>
    <li>
      <div class="post-text">
        <a href="https://www.threads.com/@<?= e($account['username']) ?>/post/<?= e($p['post_code']) ?>" target="_blank" rel="noopener noreferrer"><?= e($p['excerpt'] !== '' ? $p['excerpt'] : 'Utas tanpa teks') ?></a>
        <p class="hint">Diposting <?= e(date('d/m/Y H:i', strtotime($p['posted_at']))) ?> WIB<?php if ($p['reject_reason']): ?>. Alasan: <?= e($p['reject_reason']) ?><?php endif ?></p>
      </div>
      <div class="post-meta">
        <strong><?= $p['views'] === null ? 'Views tidak terbaca' : number_format((int) $p['views'], 0, ',', '.') . ' views' ?></strong>
        <span class="tag<?= ['approved' => ' good', 'rejected' => ' fail'][$p['status']] ?? '' ?>"><?= ['pending' => 'Menunggu review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$p['status']] ?></span>
      </div>
    </li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
