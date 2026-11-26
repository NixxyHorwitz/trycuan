<?php
use App\Models\Campaign;
$id = (int) $campaign['id'];
$state = Campaign::state($campaign);
$budget = max(1, (int) $campaign['budget']);
$fee = (int) $stats['fee'];
?>
<div class="page-head">
  <a class="back" href="/admin/campaign"><?= icon('back') ?>Semua campaign</a>
  <div class="row"><div class="flex flex-wrap items-center gap-3"><h1><?= e($campaign['title']) ?></h1><span class="tag<?= ['open' => ' good', 'paused' => ' wait', 'upcoming' => ' on'][$state] ?? '' ?>"><?= e(Campaign::label($state)) ?></span></div>
    <div class="actions">
      <?php if ($state !== 'ended' && in_array($campaign['status'], ['active', 'paused'], true)): $pause = $campaign['status'] === 'active'; ?>
      <form class="bare" method="post" action="/admin/campaign/<?= $id ?>/status" data-confirm="<?= $pause ? 'Jeda campaign ini? Freelancer tidak bisa ikut atau mengirim utas sampai dilanjutkan.' : 'Lanjutkan campaign ini?' ?>"><?= csrf_field() ?><input type="hidden" name="tindakan" value="<?= $pause ? 'jeda' : 'lanjut' ?>"><button class="btn outline" type="submit"><?= icon($pause ? 'pause' : 'play') ?><?= $pause ? 'Jeda' : 'Lanjutkan' ?></button></form>
      <?php endif ?>
      <a class="btn outline" href="/admin/campaign/<?= $id ?>/dokumen"><?= icon('book') ?>Materi</a>
      <a class="btn outline" href="/admin/campaign/<?= $id ?>/duplikat"><?= icon('copy') ?>Duplikat</a>
      <a class="btn primary" href="/admin/campaign/<?= $id ?>"><?= icon('edit') ?><?= $state === 'ended' ? 'Lihat' : 'Ubah' ?></a>
    </div></div>
  <p><?= e(date('d/m/Y H:i', strtotime($campaign['starts_at']))) ?> sampai <?= e(date('d/m/Y H:i', strtotime($campaign['ends_at']))) ?> WIB</p>
</div>
<div class="kpis">
  <div class="kpi"><span><?= icon('eye') ?>Views</span><strong><?= angka($stats['views']) ?></strong></div>
  <div class="kpi"><span><?= icon('click') ?>Klik valid</span><strong><?= angka($stats['clicks']) ?></strong><small>CTR <?= $stats['views'] > 0 ? number_format($stats['clicks'] / $stats['views'] * 100, 2, ',', '.') . '%' : '-' ?></small></div>
  <div class="kpi"><span><?= icon('users') ?>Akun</span><strong><?= (int) $stats['accounts'] ?></strong></div>
  <div class="kpi"><span><?= icon('file') ?>Utas</span><strong><?= (int) $stats['posts'] ?></strong><small><?= (int) $stats['pending'] ?> menunggu review</small></div>
</div>
<section class="card gap">
  <div class="row mb-3"><h2>Anggaran</h2><span class="hint"><?= round($fee / $budget * 100, 1) ?>% terpakai</span></div>
  <progress max="<?= $budget ?>" value="<?= min($fee, $budget) ?>" aria-label="Anggaran terpakai"></progress>
  <dl class="facts gap"><div><dt>Estimasi fee</dt><dd><?= e(rupiah($fee)) ?></dd></div><div><dt>Sudah cair</dt><dd><?= e(rupiah((int) $stats['credited'])) ?></dd></div><div><dt>Anggaran</dt><dd><?= e(rupiah((int) $campaign['budget'])) ?></dd></div><div><dt>Per 1.000 views</dt><dd><?= e(rupiah((int) $campaign['rate_per_1k'])) ?></dd></div><div><dt>Maksimum akun</dt><dd><?= e(rupiah((int) $campaign['cap_per_account'])) ?></dd></div></dl>
</section>
<div class="two gap">
  <?php foreach (['views' => ['Pertambahan views', 'line', 'Views'], 'valid' => ['Klik valid', 'bar', 'Klik']] as $key => [$name, $kind, $label]): ?>
  <section class="card"><div class="card-head"><h2><?= $name ?></h2><span class="hint">14 hari</span></div><div class="chart"><canvas role="img" aria-label="<?= e($name . ': ' . implode(', ', $chart[$key])) ?>" <?= chart_attr(['labels' => $chart['labels'], 'type' => $kind, 'sets' => [['label' => $label, 'data' => $chart[$key], 'color' => 'accent']]]) ?>></canvas></div></section>
  <?php endforeach ?>
</div>
<section class="card gap">
  <div class="card-head"><h2>Peringkat akun</h2></div>
  <?php if (!$accounts): ?><p class="hint">Belum ada akun yang ikut.</p><?php else: ?>
  <div class="table-wrap"><table class="fold"><thead><tr><th>Akun</th><th class="right">Views</th><th class="right">Klik valid</th><th class="right">CTR</th><th class="right">Utas</th><th class="right">Fee</th><th>Link</th></tr></thead><tbody>
    <?php foreach ($accounts as $a): ?><tr>
      <td><a class="font-medium hover:text-accent" href="/admin/akun/<?= (int) $a['id'] ?>">@<?= e($a['username']) ?></a></td>
      <td class="right" data-label="Views"><?= angka($a['total_views']) ?></td><td class="right" data-label="Klik valid"><?= angka($a['clicks']) ?></td>
      <td class="right" data-label="CTR"><?= $a['total_views'] > 0 ? number_format($a['clicks'] / $a['total_views'] * 100, 2, ',', '.') . '%' : '-' ?></td>
      <td class="right" data-label="Utas"><?= (int) $a['posts'] ?></td><td class="right" data-label="Fee"><?= e(rupiah((int) $a['fee_total'])) ?></td>
      <td data-label="Link"><span class="tag<?= $a['link_verified_at'] ? ' good' : ' fail' ?>"><?= $a['link_verified_at'] ? 'Terpasang' : 'Tidak' ?></span></td>
    </tr><?php endforeach ?>
  </tbody></table></div>
  <?php endif ?>
</section>
