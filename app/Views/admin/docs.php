<?php
use App\Models\CampaignDoc;
$id = (int) $campaign['id'];
?>
<div class="page-head">
  <a class="back" href="/admin/campaign/<?= $id ?>/ringkasan"><?= icon('back') ?><?= e($campaign['title']) ?></a>
  <div class="row"><h1>Materi dan panduan</h1><div class="actions"><a class="btn outline" href="/admin/campaign/<?= $id ?>/dokumen/baru?jenis=materi"><?= icon('plus') ?>Materi</a><a class="btn primary" href="/admin/campaign/<?= $id ?>/dokumen/baru"><?= icon('plus') ?>Panduan</a></div></div>
</div>
<div class="split">
  <div class="stack">
  <?php foreach (CampaignDoc::KINDS as $kind => $label): $rows = array_values(array_filter($docs, static fn (array $d): bool => $d['kind'] === $kind)); ?>
  <section class="card"><div class="card-head"><h2><?= $label ?></h2><span class="hint"><?= count($rows) ?> dokumen</span></div>
    <?php if (!$rows): ?><p class="hint"><?= $kind === 'panduan' ? 'Tulis cara mengerjakan, gaya bahasa, dan larangan.' : 'Siapkan contoh teks, poin produk, atau gambar.' ?></p><?php endif ?>
    <ul class="list"><?php foreach ($rows as $i => $d): ?>
      <li><div class="entry"><span class="bubble"><?= icon($kind === 'panduan' ? 'book' : 'layers') ?></span><div><a class="title" href="/admin/dokumen/<?= (int) $d['id'] ?>"><?= e($d['title']) ?></a><p class="hint">Diubah <?= e(date('d/m/Y H:i', strtotime($d['updated_at']))) ?></p></div></div>
        <div class="meta"><span class="tag<?= $d['status'] === 'published' ? ' good' : ' wait' ?>"><?= $d['status'] === 'published' ? 'Terbit' : 'Draf' ?></span>
        <form class="bare flex" method="post" action="/admin/dokumen/<?= (int) $d['id'] ?>"><?= csrf_field() ?>
          <button class="icon-btn" name="tindakan" value="naik" aria-label="Naikkan"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('chevron-up') ?></button><button class="icon-btn" name="tindakan" value="turun" aria-label="Turunkan"<?= $i === count($rows) - 1 ? ' disabled' : '' ?>><?= icon('down') ?></button>
          <button class="icon-btn" name="tindakan" value="hapus" aria-label="Hapus" data-confirm="Hapus dokumen <?= e($d['title']) ?>?"><?= icon('trash') ?></button></form></div></li>
    <?php endforeach ?></ul></section>
  <?php endforeach ?>
  </div>
  <section class="card"><h2>Berkas unduhan</h2>
    <form method="post" action="/admin/campaign/<?= $id ?>/berkas" enctype="multipart/form-data" data-once="Mengunggah..."><?= csrf_field() ?>
      <div class="field"><label for="berkas">Tambah berkas</label><div class="file"><input id="berkas" name="berkas" type="file" data-max="8388608" accept="image/jpeg,image/png,image/webp,application/pdf" required><?= icon('upload') ?><span><strong data-file="Pilih atau seret berkas">Pilih atau seret berkas</strong><span class="hint">Gambar atau PDF, maksimal 8 MB.</span></span></div><?php if (!empty($errors['berkas'])): ?><p class="error"><?= e($errors['berkas']) ?></p><?php endif ?></div>
      <button class="btn primary" type="submit">Unggah</button></form>
    <ul class="list mt-4"><?php foreach ($files as $f): ?>
      <li><div><a class="title" href="/admin/materi/<?= (int) $f['id'] ?>" target="_blank" rel="noopener"><?= e($f['name']) ?></a><p class="hint"><?= angka((int) ceil($f['size'] / 1024)) ?> KB. Sisipkan dengan <code>![ket](berkas:<?= (int) $f['id'] ?>)</code></p></div><form class="bare" method="post" action="/admin/materi/<?= (int) $f['id'] ?>" data-confirm="Hapus berkas ini? Gambar di dokumen ikut hilang."><?= csrf_field() ?><button class="icon-btn" aria-label="Hapus <?= e($f['name']) ?>"><?= icon('trash') ?></button></form></li>
    <?php endforeach ?></ul>
  </section>
</div>
