<?php
use App\Models\CampaignDoc;
$id = (int) $campaign['id'];
?>
<div class="page-head"><a class="back" href="/campaign/<?= $id ?>"><?= icon('back') ?><?= e($campaign['title']) ?></a><h1>Materi dan panduan</h1><p>Baca sebelum posting. Teks contoh boleh disalin, tetapi tulis ulang dengan gayamu.</p></div>
<?php if (!$docs && !$files): ?><section class="empty"><?= icon('book') ?><h2>Belum ada dokumen</h2><p>Ikuti brief di halaman campaign. Materi akan muncul di sini bila sudah disiapkan.</p></section>
<?php else: ?>
<div class="docs">
  <aside class="docs-nav">
    <details open><summary><?= icon('list') ?>Daftar isi<?= icon('down') ?></summary>
    <nav aria-label="Daftar isi">
      <?php foreach (CampaignDoc::KINDS as $kind => $label): $rows = array_filter($docs, static fn (array $d): bool => $d['kind'] === $kind); if (!$rows) continue; ?>
      <p><?= $label ?></p>
      <?php foreach ($rows as $d): ?><a href="#dok-<?= (int) $d['id'] ?>"><?= e($d['title']) ?></a><?php foreach ($d['toc'] as $t): ?><a class="deep" href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a><?php endforeach; endforeach ?>
      <?php endforeach ?>
      <?php if ($files): ?><p>Berkas</p><a href="#berkas">Unduhan</a><?php endif ?>
    </nav></details>
  </aside>
  <div class="docs-body">
    <?php foreach ($docs as $d): ?>
    <article class="sheet-doc" id="dok-<?= (int) $d['id'] ?>">
      <header><span class="tag on"><?= CampaignDoc::KINDS[$d['kind']] ?></span><h2><?= e($d['title']) ?></h2><p class="hint">Diperbarui <?= e(date('d/m/Y H:i', strtotime($d['updated_at']))) ?> WIB</p></header>
      <div class="markdown"><?= $d['html'] ?></div>
    </article>
    <?php endforeach ?>
    <?php if ($files): ?>
    <section class="sheet-doc" id="berkas"><header><h2>Berkas unduhan</h2></header>
      <ul class="list"><?php foreach ($files as $f): ?><li><div class="entry"><span class="bubble"><?= icon('file') ?></span><div><strong class="font-medium"><?= e($f['name']) ?></strong><p class="hint"><?= angka((int) ceil($f['size'] / 1024)) ?> KB</p></div></div><a class="btn outline small" href="/materi/<?= (int) $f['id'] ?>" download="<?= e($f['name']) ?>" data-turbo="false"><?= icon('download') ?>Unduh</a></li><?php endforeach ?></ul></section>
    <?php endif ?>
  </div>
</div>
<?php endif ?>
