<div class="page-head"><div class="row"><h1>Scraper</h1><span class="tag<?= $health['paused'] ? ' fail' : ' good' ?>"><?= $health['paused'] ? 'Dijeda' : 'Aktif' ?></span></div></div>
<?php if ($health['paused']): ?>
<section class="card narrow">
  <p>Dijeda sampai <?= e(date('H:i', $health['open_until'])) ?> WIB setelah kegagalan beruntun atau blokir dari Threads. Membuka lebih cepat bisa memperpanjang blokir.</p>
  <form class="gap" method="post" action="/admin/scraper" data-confirm="Buka jeda sekarang? Ini bisa memperpanjang blokir dari Threads."><?= csrf_field() ?><button class="btn outline" type="submit">Buka jeda sekarang</button></form>
</section>
<?php endif ?>
<div class="kpis<?= $health['paused'] ? ' gap' : '' ?>">
  <div class="kpi"><span>Request 24 jam</span><strong><?= (int) $health['total'] ?></strong></div>
  <div class="kpi"><span>Berhasil</span><strong><?= $health['total'] ? round($health['ok'] / $health['total'] * 100) . '%' : '-' ?></strong></div>
  <div class="kpi"><span>Rata-rata</span><strong><?= $health['total'] ? number_format($health['avg'] / 1000, 1, ',', '.') . ' dtk' : '-' ?></strong></div>
  <div class="kpi"><span>Gagal beruntun</span><strong><?= (int) $health['failures'] ?></strong></div>
</div>
<section class="card gap">
  <h2>50 request terakhir</h2>
  <?php if (!$logs): ?><p class="hint">Belum ada request.</p><?php endif ?>
  <ul class="list">
    <?php foreach ($logs as $l): ?>
    <li><div><strong><?= e($l['target']) ?></strong><p class="hint"><?= e(date('d/m H:i:s', strtotime($l['created_at']))) ?>, <?= (int) $l['duration_ms'] ?> ms<?= $l['http_code'] ? ', HTTP ' . (int) $l['http_code'] : '' ?><?= $l['detail'] ? '. ' . e($l['detail']) : '' ?></p></div>
      <div class="meta"><span class="tag<?= $l['outcome'] === 'ok' ? ' good' : (in_array($l['outcome'], ['not_found', 'login_required'], true) ? '' : ' fail') ?>"><?= e(str_replace('_', ' ', $l['outcome'])) ?></span></div></li>
    <?php endforeach ?>
  </ul>
</section>
