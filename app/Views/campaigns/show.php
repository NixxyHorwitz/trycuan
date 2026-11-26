<?php
use App\Models\Campaign;
$id = (int) $campaign['id'];
$rate = (int) ($part['rate_snapshot'] ?? $campaign['rate_per_1k']);
$cap = (int) ($part['cap_snapshot'] ?? $campaign['cap_per_account']);
?>
<div class="page-head">
  <a class="back" href="/campaign"><?= icon('back') ?>Semua campaign</a>
  <div class="row"><h1><?= e($campaign['title']) ?></h1><span class="tag<?= $state === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($state)) ?></span></div>
</div>
<?php if ($docs): ?><a class="doc-banner" href="/campaign/<?= $id ?>/panduan"><span class="bubble out"><?= icon('book') ?></span><span><strong>Materi dan panduan</strong><small><?= (int) $docs ?> dokumen. Baca sebelum posting.</small></span><?= icon('chevron') ?></a><?php endif ?>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>

<dl class="facts card">
  <div><dt>Per 1.000 views</dt><dd><?= e(rupiah($rate)) ?></dd></div>
  <div><dt>Maksimum per akun</dt><dd><?= e(rupiah($cap)) ?></dd></div>
  <div><dt>Mulai</dt><dd><?= e(date('d/m/Y H:i', strtotime($campaign['starts_at']))) ?></dd></div>
  <div><dt>Selesai</dt><dd><?= e(date('d/m/Y H:i', strtotime($campaign['ends_at']))) ?> WIB</dd></div>
</dl>

<div class="split">
  <section class="card">
    <h2>Brief</h2>
    <p class="prose"><?= nl2br(e($campaign['brief'])) ?></p>
    <h2 class="sub">Syarat utas</h2>
    <ul class="rules">
      <li>Link pribadimu terpasang di profil sebelum posting dan selama campaign.</li>
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
    <form class="gap" method="post" action="/campaign/<?= $id ?>/ikut" data-once="Mendaftar..." data-confirm="Akun kamu terikat ke campaign ini sampai selesai."><?= csrf_field() ?><button class="btn primary" type="submit">Ikut campaign</button></form>
    <?php endif ?>
  <?php else: ?>
    <h2>Kirim utas</h2>
    <?php if ($state === 'open' && !$linked): ?>
    <?php if (!empty($errors['url'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['url']) ?></span></div><?php endif ?>
    <p class="hint">Pasang link pribadimu di profil Threads dulu. Utas yang diposting sebelum link terdeteksi akan ditolak.</p>
    <a class="btn primary gap" href="/akun-threads#link">Pasang link</a>
    <?php elseif ($state === 'open'): ?>
    <form method="post" action="/campaign/<?= $id ?>/utas" data-once="Memeriksa utas...">
      <?= csrf_field() ?>
      <?php (function () use ($errors, $old) { $key = 'url'; $label = 'Link utas'; $type = 'text'; $value = $old['url']; $error = $errors['url'] ?? null; $hint = 'Salin dari titik tiga, tombol bagikan, atau alamat browser.'; $attrs = App\Services\Threads\PostLink::FIELD; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <button class="btn primary" type="submit">Kirim utas</button>
    </form>
    <?php else: ?><p class="hint">Campaign sedang tidak menerima utas.</p><?php endif ?>
    <?php if (!$posts): ?>
    <form class="gap" method="post" action="/campaign/<?= $id ?>/batal" data-confirm="Keluar dari campaign ini?"><?= csrf_field() ?><button class="btn outline" type="submit">Keluar dari campaign</button></form>
    <?php endif ?>
  <?php endif ?>
  </section>
</div>

<?php if ($part): ?>
<section class="card gap" data-filter-group>
  <div class="card-head"><h2>Utas kamu (<?= count($posts) ?>)</h2><?php if ($posts && $reportable): ?><a class="btn outline" href="/campaign/<?= $id ?>/setor"><?= icon('upload') ?>Setor views</a><?php endif ?></div>
  <?php if ($posts): ?><div class="chips mx-0! px-0!"><?php foreach (['' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label): ?><button class="chip" type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key === '' ? 'true' : 'false' ?>"><?= $label ?></button><?php endforeach ?></div><p class="hint" data-none hidden>Tidak ada utas dengan status ini.</p><?php endif ?>
  <?php if (!$posts): ?><p class="hint">Belum ada utas. Posting sesuai brief, lalu kirim link-nya. Views boleh menyusul.</p><?php endif ?>
  <ul class="list">
    <?php $username = $account['username']; $reportTo = $reportable ? $id : null; foreach ($posts as $p) require ROOT . '/app/Views/partials/post_row.php' ?>
  </ul>
</section>
<?php endif ?>
