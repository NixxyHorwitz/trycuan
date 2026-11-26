<div class="page-head"><h1>Pengumuman</h1><p>Kabar dari tim <?= e(setting('site_name')) ?>.</p></div>
<?php if (!$rows): ?><section class="empty"><?= icon('bell') ?><h2>Belum ada pengumuman</h2><p>Kabar campaign dan pembayaran akan muncul di sini.</p></section><?php endif ?>
<div class="stack narrow">
  <?php foreach ($rows as $r): ?>
  <article class="card">
    <div class="tags"><?php if ($r['level'] === 'penting'): ?><span class="tag wait">Penting</span><?php endif ?><?php if ((int) $r['id'] > $seen): ?><span class="tag on">Baru</span><?php endif ?><span class="hint"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?> WIB</span></div>
    <h2 class="mt-2 mb-1"><?= e($r['title']) ?></h2>
    <p class="prose text-soft"><?= nl2br(e($r['body'])) ?></p>
  </article>
  <?php endforeach ?>
</div>
