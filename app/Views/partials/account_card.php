<?php
/** Variabel: $card dari AccountCard::build */
use App\Models\Campaign;
use App\Services\AccountCard;
['part' => $part, 'campaign' => $campaign, 'posts' => $posts] = $card;
$views = (int) $part['total_views'];
$rest = $views % 1000;
$installed = $card['link']['link_verified_at'] !== null;
$minutes = $card['updated'] ? max(0, intdiv(time() - strtotime($card['updated']), 60)) : null;
$ago = $minutes === null ? 'Belum ada utas' : 'Data tertua ' . ($minutes < 1 ? 'baru saja' : ($minutes < 60 ? "$minutes menit lalu" : ($minutes < 1440 ? intdiv($minutes, 60) . ' jam lalu' : intdiv($minutes, 1440) . ' hari lalu')));
?>
<section class="stack" id="account-card" data-page="/dashboard?campaign=<?= (int) $campaign['id'] ?>" data-csrf="<?= e(App\Core\Csrf::token()) ?>" data-stale="<?= e(implode(',', $card['stale'])) ?>">
<div class="card stack">
  <div class="row">
    <div class="entry"><?= avatar_of($card['account']['user_id'], $card['account']['username']) ?><div><h2>@<?= e($card['account']['username']) ?></h2><p class="hint" data-status role="status"><?= e($ago) ?><?= $card['partial'] ? '. Sebagian belum terbaru' : '' ?></p></div></div>
    <div class="tags"><span class="tag<?= $card['state'] === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($card['state'])) ?></span><span class="tag<?= $installed ? ' good' : ' fail' ?>"><?= $installed ? 'Link terpasang' : 'Link tidak terpasang' ?></span></div>
  </div>
  <div class="gauge-wrap">
    <div class="gauge" role="img" aria-label="<?= $rest ?> dari 1.000 views menuju fee berikutnya">
      <svg viewBox="0 0 180 104"><path class="track" d="M14 94a76 76 0 0 1 152 0" pathLength="100"/><path class="fill" d="M14 94a76 76 0 0 1 152 0" pathLength="100" stroke-dashoffset="100" data-value="<?= $card['capped'] ? 100 : round($rest / 10, 1) ?>"/></svg>
      <p><strong><?= $card['capped'] ? 'Penuh' : angka($rest) ?></strong><span><?= $card['capped'] ? 'batas tercapai' : 'dari 1.000' ?></span></p>
    </div>
    <div class="stack gap-3!">
      <?php if ($card['capped']): ?><p>Batas fee <?= e(rupiah((int) $part['cap_snapshot'])) ?> per akun sudah tercapai.</p>
      <?php elseif ($card['budgetHit']): ?><p>Anggaran campaign hampir atau sudah habis, jadi estimasi tidak bertambah.</p>
      <?php else: ?><p class="text-base font-medium"><?= angka(1000 - $rest) ?> views lagi untuk <span class="accent font-semibold">+<?= e(rupiah((int) $part['rate_snapshot'])) ?></span></p><?php endif ?>
      <div class="actions justify-center sm:justify-start">
        <?php if ($card['refreshable']): ?><button class="btn primary" type="button" data-refresh-all><?= icon('refresh') ?><span>Refresh views</span></button><?php endif ?>
        <a class="btn outline" href="/campaign/<?= (int) $campaign['id'] ?>">Detail campaign</a>
      </div>
    </div>
  </div>
  <div class="metrics border-t border-line pt-4">
    <div><span>Total views</span><strong><?= angka($views) ?></strong></div>
    <div><span>Estimasi fee</span><strong class="accent"><?= e(rupiah((int) $part['fee_total'])) ?></strong></div>
    <div><span>Klik valid</span><strong><?= angka($card['clicks']) ?></strong></div>
    <div><span>Utas dihitung</span><strong><?= (int) $card['counted'] ?></strong></div>
  </div>
  <p class="hint border-t border-line pt-4"><?php
  if ($part['trust_status'] === 'rejected') echo 'Pencairan campaign ini ditolak setelah pemeriksaan. Hubungi admin untuk banding.';
  else {
      echo 'Sudah masuk saldo ' . e(rupiah((int) $part['fee_credited'])) . '. ';
      if ($card['credit']['ready']) echo e(rupiah($card['credit']['ready'])) . ' menunggu pemeriksaan admin. ';
      if ($card['credit']['next']) echo $card['credit']['next'] > time() ? 'Sisanya ditahan sampai ' . e(date('d/m/Y H:i', $card['credit']['next'])) . ' WIB, lalu refresh lagi.' : 'Masa tahan selesai, tekan Refresh supaya bisa diperiksa.';
      elseif (!$card['credit']['ready']) echo 'Hanya utas yang disetujui yang dihitung.';
  }
  ?></p>
</div>
<div class="two">
  <?php $week = []; for ($i = 6; $i >= 0; $i--) $week[] = date('d/m', strtotime("-$i days"));
  foreach (['Total views' => $card['viewDays'], 'Klik valid' => $card['clickDays']] as $name => $values): ?>
  <div class="card"><div class="card-head"><h2><?= e($name) ?></h2><span class="hint">7 hari</span></div><div class="chart mini"><canvas role="img" aria-label="<?= e($name . ': ' . implode(', ', $values)) ?>" <?= chart_attr(['labels' => $week, 'type' => $name === 'Klik valid' ? 'bar' : 'line', 'sets' => [['label' => $name, 'data' => $values, 'color' => 'accent']]]) ?>></canvas></div></div>
  <?php endforeach ?>
</div>
<?php if ($posts): ?>
<div class="card" data-filter-group>
  <div class="card-head"><h2>Utas kamu</h2><a class="hint" href="/campaign/<?= (int) $campaign['id'] ?>">Lihat semua <?= count($posts) ?></a></div>
  <ul class="list">
    <?php $username = $card['account']['username']; $refreshRow = $card['refreshable']; $reportTo = App\Services\ViewReports::open($campaign) ? (int) $campaign['id'] : null; foreach (array_slice($posts, 0, 5) as $p) require ROOT . '/app/Views/partials/post_row.php' ?>
  </ul>
</div>
<?php endif ?>
</section>
