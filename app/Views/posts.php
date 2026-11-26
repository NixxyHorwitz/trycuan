<?php
use App\Services\ViewReports;
$link = static fn (array $over): string => '/utas' . (($q = http_build_query(array_filter($over + ['status' => $status, 'campaign' => $campaign ?: '', 'cari' => $text]))) ? "?$q" : '');
$tabs = ['' => ['Semua', $stats['total']], 'pending' => ['Menunggu', $stats['pending']], 'approved' => ['Disetujui', $stats['approved']], 'rejected' => ['Ditolak', $stats['rejected']], 'kosong' => ['Belum ada views', $stats['blank']]];
?>
<div class="page-head row"><div><h1>Utas</h1><p>Semua utas yang sudah kamu kirim.</p></div><a class="btn primary" href="/campaign"><?= icon('send') ?>Kirim utas</a></div>
<?php if (!$account): ?><section class="empty"><?= icon('threads') ?><h2>Hubungkan akun Threads dulu</h2><p>Utas yang kamu kirim akan terkumpul di sini.</p><a class="btn primary" href="/akun-threads">Verifikasi akun</a></section>
<?php elseif (!$stats['total']): ?><section class="empty"><?= icon('file') ?><h2>Belum ada utas</h2><p>Ikut campaign, posting di Threads, lalu kirim link-nya.</p><a class="btn primary" href="/campaign">Lihat campaign</a></section>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><span><?= icon('file') ?>Terkirim</span><strong><?= angka($stats['total']) ?></strong><small><?= angka($stats['pending']) ?> menunggu review</small></div>
  <div class="kpi"><span><?= icon('check') ?>Disetujui</span><strong><?= angka($stats['approved']) ?></strong><small><?= angka($stats['rejected']) ?> ditolak</small></div>
  <div class="kpi"><span><?= icon('eye') ?>Views dihitung</span><strong><?= angka($stats['views']) ?></strong><small>dari utas disetujui</small></div>
  <div class="kpi<?= $stats['blank'] ? ' todo' : '' ?>"><span><?= icon('upload') ?>Belum ada views</span><strong><?= angka($stats['blank']) ?></strong><small>bisa setor insight</small></div>
</div>
<form class="toolbar bare gap" method="get" action="/utas" data-turbo-action="replace">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="search"><?= icon('search') ?><input type="search" name="cari" value="<?= e($text) ?>" placeholder="Cari isi utas" aria-label="Cari isi utas" maxlength="60"></div>
  <?php if (count($campaigns) > 1): ?><select name="campaign" data-submit aria-label="Campaign"><option value="0">Semua campaign</option><?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['campaign_id'] ?>"<?= $campaign === (int) $c['campaign_id'] ? ' selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach ?></select><?php endif ?>
</form>
<div class="chips"><?php foreach ($tabs as $key => [$label, $n]): ?><a class="chip" href="<?= e($link(['status' => $key])) ?>" data-turbo-action="replace"<?= $status === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= angka($n) ?></b></a><?php endforeach ?></div>
<?php if (!$posts): ?><section class="empty"><?= icon('search') ?><p>Tidak ada utas yang cocok.</p></section><?php endif ?>
<div class="post-list">
<?php foreach ($posts as $p):
    $counted = $p['status'] === 'approved' && $p['missing_at'] === null;
    $steps = [['Terkirim', true], [$p['status'] === 'rejected' ? 'Ditolak' : 'Disetujui', $p['status'] === 'approved'], ['Views tercatat', $p['views'] !== null], ['Masuk hitungan', $counted && $p['views'] !== null]];
    $report = ViewReports::open(['status' => $p['campaign_status'], 'starts_at' => $p['starts_at'], 'ends_at' => $p['ends_at']]) && $p['status'] !== 'rejected';
?>
<article class="card post">
  <div class="row"><a class="hint" href="/campaign/<?= (int) $p['campaign_id'] ?>"><?= e($p['title']) ?></a><span class="hint"><?= e(date('d/m/Y H:i', strtotime($p['posted_at']))) ?> WIB</span></div>
  <p class="post-text"><?= e($p['excerpt'] !== '' ? $p['excerpt'] : 'Utas tanpa teks') ?></p>
  <ol class="track"><?php foreach ($steps as [$name, $done]): ?><li class="<?= $name === 'Ditolak' ? 'fail' : ($done ? 'done' : '') ?>"><span><?= icon($name === 'Ditolak' ? 'close' : 'check') ?></span><?= $name ?></li><?php endforeach ?></ol>
  <?php if ($p['reject_reason']): ?><p class="error">Alasan: <?= e($p['reject_reason']) ?></p><?php endif ?>
  <?php if ($p['missing_at']): ?><p class="error">Utas tidak ditemukan di Threads, jadi tidak dihitung.</p><?php endif ?>
  <div class="row"><strong class="tabular-nums"><?= $p['views'] === null ? 'Views belum ada' : angka($p['views']) . ' views' ?><?php if ($p['view_source'] === 'insight' && $p['views'] !== null): ?> <span class="tag">Insight</span><?php endif ?></strong>
    <div class="actions"><?php if ($p['reported']): ?><span class="tag wait">Setoran ditinjau</span><?php elseif ($report): ?><a class="btn outline small" href="/campaign/<?= (int) $p['campaign_id'] ?>/setor?utas=<?= (int) $p['id'] ?>"><?= icon('upload') ?>Setor views</a><?php endif ?><a class="btn ghost small" href="https://www.threads.com/@<?= e($account['username']) ?>/post/<?= e($p['post_code']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?>Buka</a></div></div>
</article>
<?php endforeach ?>
</div>
<?php if ($pages > 1): ?><nav class="pager" aria-label="Halaman"><?php if ($page > 1): ?><a class="btn outline" href="<?= e($link(['halaman' => $page - 1])) ?>">Sebelumnya</a><?php else: ?><span></span><?php endif ?><span class="hint"><?= $page ?> dari <?= $pages ?></span><?php if ($page < $pages): ?><a class="btn outline" href="<?= e($link(['halaman' => $page + 1])) ?>">Berikutnya</a><?php else: ?><span></span><?php endif ?></nav><?php endif ?>
<?php endif ?>
