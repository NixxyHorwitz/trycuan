<?php
use App\Services\AccountTrustEvaluator as Trust;
$id = (int) $part['id'];
?>
<div class="page-head"><a class="back" href="/admin/fee"><?= icon('back') ?>Pencairan fee</a><div class="row"><h1>@<?= e($part['username']) ?></h1><span class="tag<?= ['wajar' => ' good', 'review' => ' fail', 'ditolak' => ' fail'][$trust['status']] ?? '' ?>"><?= e(Trust::LABELS[$trust['status']]) ?></span></div><p><?= e($campaign['title']) ?>, <a href="/admin/user/<?= (int) $part['user_id'] ?>">lihat user</a></p></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<section class="card">
  <dl class="facts">
    <div><dt>Views</dt><dd><?= number_format((int) $part['total_views'], 0, ',', '.') ?></dd></div>
    <div><dt>Klik valid</dt><dd><?= $trust['valid'] ?></dd></div>
    <div><dt>Ditahan</dt><dd><?= $trust['held'] ?></dd></div>
    <div><dt>Tidak valid</dt><dd><?= $trust['invalid'] ?></dd></div>
    <div><dt>CTR</dt><dd><?= $trust['ctr'] === null ? '-' : number_format($trust['ctr'], 3, ',', '.') . '%' ?></dd></div>
    <div><dt>Median akun lain</dt><dd><?= $trust['median'] === null ? '-' : number_format($trust['median'], 3, ',', '.') . '%' ?></dd></div>
    <div><dt>Estimasi</dt><dd><?= e(rupiah((int) $part['fee_total'])) ?></dd></div>
    <div><dt>Siap</dt><dd><?= e(rupiah($credit['ready'])) ?></dd></div>
  </dl>
  <?php if ($trust['reasons']): ?><ul class="rules gap"><?php foreach ($trust['reasons'] as $reason): ?><li><?= e(Trust::REASONS[$reason]) ?></li><?php endforeach ?></ul><?php endif ?>
</section>
<div class="two gap">
  <section class="card">
    <h2>7 hari terakhir</h2>
    <?php $labels = array_map(static fn (string $d): string => date('d/m', strtotime($d)), array_keys($clicks));
    foreach (['Total views' => [$views, 'line'], 'Klik valid' => [array_values($clicks), 'bar']] as $name => [$values, $kind]): ?>
    <p class="hint mt-3"><?= $name ?></p><div class="chart mini"><canvas role="img" aria-label="<?= e($name . ': ' . implode(', ', $values)) ?>" <?= chart_attr(['labels' => $labels, 'type' => $kind, 'sets' => [['label' => $name, 'data' => $values, 'color' => 'accent']]]) ?>></canvas></div>
    <?php endforeach ?>
    <div class="table-wrap gap"><table><thead><tr><th>Tanggal</th><th class="right">Total views</th><th class="right">Klik valid</th></tr></thead><tbody>
      <?php foreach (array_keys($clicks) as $i => $day): ?><tr><td><?= e(date('d/m', strtotime($day))) ?></td><td class="right"><?= number_format($views[$i], 0, ',', '.') ?></td><td class="right"><?= (int) $clicks[$day] ?></td></tr><?php endforeach ?>
    </tbody></table></div>
  </section>
  <section class="card">
    <h2>Alasan filter</h2>
    <?php if (!$reasons): ?><p class="hint">Belum ada klik yang ditahan atau dibuang.</p><?php endif ?>
    <ul class="pairs"><?php foreach ($reasons as $reason => $total): ?><li><span><?= e(str_replace('_', ' ', $reason)) ?></span><strong><?= (int) $total ?></strong></li><?php endforeach ?></ul>
    <?php if ($trust['held']): ?>
    <form class="gap" method="post" action="/admin/akun/<?= $id ?>">
      <?= csrf_field() ?>
      <p class="hint">Keputusan berlaku untuk <?= $trust['held'] ?> klik yang ditahan di akun ini.</p>
      <div class="field"><label for="alasan">Alasan</label><input id="alasan" name="alasan" type="text" minlength="3" maxlength="300" autocomplete="off" required></div>
      <div class="actions"><button class="btn danger" name="keputusan" value="buang" data-confirm="Buang semua klik yang ditahan di akun ini?">Buang semua</button><button class="btn outline" name="keputusan" value="terima">Nyatakan valid</button></div>
    </form>
    <?php endif ?>
  </section>
</div>
