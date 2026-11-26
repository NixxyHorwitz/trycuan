<?php use App\Services\AccountTrustEvaluator as Trust; ?>
<?php
$groups = ['siap' => [], 'tahan' => [], 'ditolak' => []];
foreach ($rows as $r) $groups[$r['trust_status'] === 'rejected' ? 'ditolak' : ($r['ready'] ? 'siap' : 'tahan')][] = $r;
$tab = isset($groups[$tab]) ? $tab : '';
$ready = array_sum(array_column($groups['siap'], 'ready'));
?>
<div class="page-head row"><div><h1>Pencairan fee</h1><p>Bandingkan views dan klik sebelum mengkreditkan.</p></div><div class="text-right"><p class="hint">Total siap</p><p class="big text-ink!"><?= e(rupiah($ready)) ?></p></div></div>
<div class="chips"><?php foreach (['' => 'Semua', 'siap' => 'Siap ditinjau', 'tahan' => 'Masa tahan', 'ditolak' => 'Ditolak'] as $key => $label): ?><a class="chip" href="/admin/fee<?= $key === '' ? '' : "?status=$key" ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= $key === '' ? count($rows) : count($groups[$key]) ?></b></a><?php endforeach ?></div>
<?php $rows = $tab === '' ? $rows : $groups[$tab]; ?>
<?php if (!$rows): ?><section class="empty"><?= icon('coins') ?><h2>Belum ada fee di sini</h2><p>Fee muncul setelah akun melewati 1.000 views.</p></section><?php endif ?>
<div class="cards wide">
<?php foreach ($rows as $r): $id = (int) $r['id']; $views = (int) $r['total_views']; $rejected = $r['trust_status'] === 'rejected'; $trust = $r['trust']; ?>
<section class="card">
  <div class="tags">
    <span class="tag<?= $rejected ? ' fail' : ($r['ready'] ? ' on' : '') ?>"><?= $rejected ? 'Ditolak' : ($r['ready'] ? 'Siap ditinjau' : 'Masa tahan') ?></span>
    <?php if (!$rejected): ?><span class="tag<?= ['wajar' => ' good', 'review' => ' fail'][$trust['status']] ?? '' ?>"><?= e(Trust::LABELS[$trust['status']]) ?></span><?php endif ?>
  </div>
  <h2><a class="title" href="/admin/akun/<?= $id ?>">@<?= e($r['username']) ?></a></h2>
  <p class="hint"><?= e($r['title']) ?></p>
  <dl class="facts gap grid-cols-4!">
    <div><dt>Views</dt><dd><?= number_format($views, 0, ',', '.') ?></dd></div>
    <div><dt>Klik valid</dt><dd><?= $trust['valid'] ?></dd></div>
    <div><dt>Ditahan</dt><dd><?= $trust['held'] ?></dd></div>
    <div><dt>Tidak valid</dt><dd><?= $trust['invalid'] ?></dd></div>
    <div><dt>CTR</dt><dd><?= $trust['ctr'] === null ? '-' : number_format($trust['ctr'], 3, ',', '.') . '%' ?></dd></div>
    <div><dt>Sudah cair</dt><dd><?= e(rupiah((int) $r['fee_credited'])) ?></dd></div>
    <div><dt>Siap</dt><dd><?= e(rupiah($r['ready'])) ?></dd></div>
    <div><dt>Estimasi</dt><dd><?= e(rupiah((int) $r['fee_total'])) ?></dd></div>
  </dl>
  <?php if ($r['next'] && !$rejected): ?><p class="hint gap"><?= $r['next'] > time() ? 'Bagian berikutnya lewat masa tahan ' . e(date('d/m/Y H:i', $r['next'])) . ' WIB.' : 'Masa tahan selesai, menunggu views dibaca ulang oleh freelancer.' ?></p><?php endif ?>
  <?php if ($trust['reasons']): ?><p class="hint gap"><?= e(implode('. ', array_map(static fn (string $x): string => Trust::REASONS[$x], $trust['reasons']))) ?>.</p><?php endif ?>
  <form class="mt-4 border-t border-line pt-4" method="post" action="/admin/fee/<?= $id ?>">
    <?= csrf_field() ?>
    <?php if (isset($errors[$id])): ?><p class="error" role="alert"><?= e($errors[$id]) ?></p><?php endif ?>
    <?php if ($rejected): ?>
    <button class="btn outline" name="keputusan" value="pulihkan">Pulihkan</button>
    <?php else: ?>
    <input type="hidden" name="nominal" value="<?= (int) $r['ready'] ?>">
    <div class="field"><label for="alasan-<?= $id ?>">Alasan bila ditolak</label><input id="alasan-<?= $id ?>" name="alasan" type="text" maxlength="300" autocomplete="off"></div>
    <div class="actions">
      <button class="btn danger" name="keputusan" value="tolak" data-confirm="Batalkan fee yang belum cair untuk akun ini?">Tolak</button>
      <?php if ($r['ready']): ?><button class="btn primary" name="keputusan" value="kreditkan" data-confirm="Masukkan <?= e(rupiah($r['ready'])) ?> ke saldo @<?= e($r['username']) ?>?">Kreditkan <?= e(rupiah($r['ready'])) ?></button><?php endif ?>
    </div>
    <?php endif ?>
  </form>
</section>
<?php endforeach ?>
</div>
