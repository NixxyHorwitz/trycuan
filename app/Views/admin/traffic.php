<?php
$change = static function (int $now, int $before): string {
    if (!$before) return 'periode lalu belum ada data';
    $p = round(($now - $before) / $before * 100);
    return ($p >= 0 ? 'naik ' : 'turun ') . abs($p) . '% dari periode lalu';
};
$link = static fn (array $over): string => '/admin/trafik?' . http_build_query($over + ['hari' => $days, 'siapa' => $guests ? 'tamu' : '']);
$bars = static function (string $title, array $rows, string $empty, array $names = []): void {
    $max = max([1, ...array_map('intval', array_column($rows, 'visitors'))]); ?>
<section class="card"><div class="card-head"><h2><?= e($title) ?></h2><span class="hint">Pengunjung</span></div>
  <?php if (!$rows): ?><p class="hint"><?= e($empty) ?></p><?php endif ?>
  <ul class="bars-list"><?php foreach ($rows as $row): ?><li><div class="row"><span><?= e($names[$row['label']] ?? (string) $row['label']) ?></span><strong><?= angka($row['visitors']) ?></strong></div><progress max="<?= $max ?>" value="<?= (int) $row['visitors'] ?>" aria-hidden="true"></progress></li><?php endforeach ?></ul></section>
<?php };
$perVisitor = $r['now']['visitors'] ? number_format($r['now']['views'] / $r['now']['visitors'], 1, ',', '.') : '-';
$period = $days === 1 ? 'Hari ini' : "$days hari";
?>
<div class="page-head row"><div><h1>Trafik pengunjung</h1><p>Tanpa cookie pelacak. Bot dan halaman admin tidak dihitung.</p></div><a class="btn outline" href="/admin/trafik/ekspor" data-turbo="false"><?= icon('download') ?>Ekspor 90 hari</a></div>
<div class="chips">
  <?php foreach ([1 => 'Hari ini', 7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $n => $name): ?><a class="chip" href="<?= e($link(['hari' => $n])) ?>"<?= $days === $n ? ' aria-current="true"' : '' ?>><?= $name ?></a><?php endforeach ?>
  <a class="chip" href="<?= e($link(['siapa' => $guests ? '' : 'tamu'])) ?>"<?= $guests ? ' aria-current="true"' : '' ?>><?= icon('users') ?>Tanpa anggota</a>
</div>
<div class="kpis">
  <div class="kpi"><span><?= icon('users') ?>Pengunjung</span><strong><?= angka($r['now']['visitors']) ?></strong><small><?= e($change($r['now']['visitors'], $r['before']['visitors'])) ?></small></div>
  <div class="kpi"><span><?= icon('eye') ?>Tayangan</span><strong><?= angka($r['now']['views']) ?></strong><small><?= $perVisitor ?> per pengunjung</small></div>
  <div class="kpi"><span><?= icon('up') ?>Satu halaman saja</span><strong><?= $r['bounce'] === null ? '-' : $r['bounce'] . '%' ?></strong><small>pergi tanpa lanjut</small></div>
  <div class="kpi"><span><?= icon('activity') ?>Sedang online</span><strong><?= angka($r['live']) ?></strong><small>5 menit terakhir</small></div>
</div>
<div class="split gap">
  <section class="card"><div class="card-head"><h2>Kunjungan</h2><span class="hint"><?= $period ?></span></div><div class="chart"><canvas role="img" aria-label="Pengunjung per hari: <?= e(implode(', ', $r['visitors'])) ?>" <?= chart_attr(['labels' => $r['labels'], 'type' => $days === 1 ? 'bar' : 'line', 'sets' => [['label' => 'Pengunjung', 'data' => $r['visitors'], 'color' => 'accent'], ['label' => 'Tayangan', 'data' => $r['views'], 'color' => 'field']]]) ?>></canvas></div></section>
  <section class="card"><div class="card-head"><h2>Jam ramai</h2><span class="hint">WIB</span></div><div class="chart"><canvas role="img" aria-label="Tayangan per jam" <?= chart_attr(['labels' => array_map(static fn (int $h): string => sprintf('%02d', $h), range(0, 23)), 'type' => 'bar', 'sets' => [['label' => 'Tayangan', 'data' => $r['hours'], 'color' => 'accent']]]) ?>></canvas></div></section>
</div>
<div class="two gap">
  <?php
  $bars('Sumber', $r['sources'], 'Belum ada kunjungan.');
  $bars('Halaman', $r['pages'], 'Belum ada kunjungan.');
  ?>
</div>
<div class="cards gap">
  <?php
  $bars('Perangkat', $r['devices'], 'Belum ada data.', ['mobile' => 'HP', 'tablet' => 'Tablet', 'desktop' => 'Komputer']);
  $bars('Browser', $r['browsers'], 'Belum ada data.');
  $bars('Sistem', $r['systems'], 'Belum ada data.');
  $bars('Negara', $r['countries'], 'Terisi bila situs memakai Cloudflare.');
  $bars('UTM campaign', $r['campaigns'], 'Tambahkan ?utm_source=...&utm_campaign=... pada link promosi.');
  ?>
</div>
