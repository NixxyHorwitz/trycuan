<?php
$ctr = (int) $totals['views'] > 0 ? number_format($totals['clicks'] / $totals['views'] * 100, 2, ',', '.') . '%' : '-';
$names = ['submission' => 'Utas', 'campaign' => 'Campaign', 'fee' => 'Fee', 'withdrawal' => 'Penarikan', 'user' => 'User', 'admin' => 'Admin', 'settings' => 'Pengaturan'];
?>
<div class="page-head row"><div><h1>Ringkasan</h1><p><?= e(date('d/m/Y')) ?>, <?= (int) $totals['running'] ?> campaign berjalan</p></div>
  <div class="actions"><a class="btn outline" href="/admin/pengumuman"><?= icon('bell') ?>Umumkan</a><a class="btn primary" href="/admin/campaign/baru"><?= icon('plus') ?>Campaign baru</a></div></div>
<?php if ($insight = App\Models\ViewReport::pendingCount()): ?><div class="notice"><?= icon('eye') ?><span><?= $insight ?> setoran views menunggu ditinjau. <a href="/admin/insight">Tinjau</a></span></div><?php endif ?>
<?php if ($scraper['paused']): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span>Scraper dijeda sampai <?= e(date('H:i', $scraper['open_until'])) ?> WIB. <a href="/admin/scraper">Periksa</a></span></div><?php endif ?>
<div class="kpis">
  <a class="kpi<?= $pending ? ' todo' : '' ?>" href="/admin/utas"><span><?= icon('file') ?>Review utas</span><strong><?= (int) $pending ?></strong><small><?= $pending ? 'menunggu keputusan' : 'semua sudah ditinjau' ?></small></a>
  <a class="kpi<?= $fees ? ' todo' : '' ?>" href="/admin/fee"><span><?= icon('coins') ?>Fee siap</span><strong><?= (int) $fees ?></strong><small>akun siap dikreditkan</small></a>
  <a class="kpi<?= $withdrawals ? ' todo' : '' ?>" href="/admin/penarikan"><span><?= icon('bank') ?>Penarikan</span><strong><?= (int) $withdrawals ?></strong><small>menunggu transfer</small></a>
  <a class="kpi" href="/admin/user"><span><?= icon('users') ?>Freelancer aktif</span><strong><?= (int) $counts['active'] ?></strong><small><?= (int) $counts['blocked'] ?> diblokir</small></a>
</div>
<div class="split gap">
  <section class="card">
    <div class="card-head"><h2>Pertambahan views</h2><div class="actions"><?php foreach ([7, 14, 30] as $n): ?><a class="chip" href="/admin?hari=<?= $n ?>" data-turbo-action="replace"<?= $days === $n ? ' aria-current="true"' : '' ?>><?= $n ?> hari</a><?php endforeach ?></div></div>
    <p class="big"><?= angka(array_sum($chart['views'])) ?></p>
    <div class="chart gap"><canvas role="img" aria-label="Pertambahan views per hari: <?= e(implode(', ', $chart['views'])) ?>" <?= chart_attr(['labels' => $chart['labels'], 'sets' => [['label' => 'Views', 'data' => $chart['views'], 'color' => 'accent']]]) ?>></canvas></div>
  </section>
  <section class="card">
    <div class="card-head"><h2>Uang</h2></div>
    <ul class="pairs">
      <li><span class="text-soft">Estimasi belum dikreditkan</span><strong><?= e(rupiah((int) $totals['waiting'])) ?></strong></li>
      <li><span class="text-soft">Saldo freelancer</span><strong><?= e(rupiah((int) $totals['owed'])) ?></strong></li>
      <li><span class="text-soft">Sudah dibayar</span><strong><?= e(rupiah((int) $totals['paid'])) ?></strong></li>
      <li><span class="text-soft">Total views</span><strong><?= angka($totals['views']) ?></strong></li>
      <li><span class="text-soft">Klik valid</span><strong><?= angka($totals['clicks']) ?></strong></li>
      <li><span class="text-soft">CTR keseluruhan</span><strong><?= $ctr ?></strong></li>
    </ul>
  </section>
</div>
<div class="two gap">
  <section class="card">
    <div class="card-head"><h2>Klik</h2><div class="tags"><span class="tag on">Valid <?= angka(array_sum($chart['valid'])) ?></span><span class="tag">Ditahan dan ditolak <?= angka(array_sum($chart['other'])) ?></span></div></div>
    <div class="chart"><canvas role="img" aria-label="Klik valid per hari: <?= e(implode(', ', $chart['valid'])) ?>" <?= chart_attr(['labels' => $chart['labels'], 'type' => 'bar', 'sets' => [['label' => 'Valid', 'data' => $chart['valid'], 'color' => 'accent'], ['label' => 'Ditahan dan ditolak', 'data' => $chart['other'], 'color' => 'field']]]) ?>></canvas></div>
  </section>
  <section class="card">
    <div class="card-head"><h2>Fee dikreditkan</h2><span class="big"><?= e(rupiah(array_sum($chart['fee']))) ?></span></div>
    <div class="chart"><canvas role="img" aria-label="Fee per hari: <?= e(implode(', ', $chart['fee'])) ?>" <?= chart_attr(['labels' => $chart['labels'], 'type' => 'bar', 'prefix' => 'Rp', 'sets' => [['label' => 'Fee', 'data' => $chart['fee'], 'color' => 'accent']]]) ?>></canvas></div>
  </section>
</div>
<div class="two gap">
  <section class="card">
    <div class="card-head"><h2>Akun teratas</h2><a class="hint" href="/admin/user?urut=views">Lihat semua</a></div>
    <?php if (!$top): ?><p class="hint">Belum ada akun yang ikut campaign.</p><?php endif ?>
    <ul class="list"><?php foreach ($top as $i => $t): ?>
      <li><div class="entry"><span class="avatar"><?= $i + 1 ?></span><div><a class="title" href="/admin/akun/<?= (int) $t['id'] ?>">@<?= e($t['username']) ?></a><p class="hint"><?= e($t['title']) ?></p></div></div><div class="meta"><span class="hint"><?= angka($t['clicks']) ?> klik</span><strong><?= angka($t['total_views']) ?> views</strong></div></li>
    <?php endforeach ?></ul>
  </section>
  <section class="card">
    <div class="card-head"><h2>Aktivitas terbaru</h2><a class="hint" href="/admin/audit">Buka audit</a></div>
    <?php if (!$logs): ?><p class="hint">Belum ada aktivitas.</p><?php endif ?>
    <ul class="list"><?php foreach ($logs as $l): ?>
      <li><div class="entry"><span class="bubble"><?= icon('history') ?></span><div><strong class="font-medium"><?= e($l['action']) ?></strong><p class="hint"><?= e($l['name'] ?? 'Sistem') ?>, <?= e($l['target']) ?></p></div></div><span class="hint"><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></span></li>
    <?php endforeach ?></ul>
  </section>
</div>
