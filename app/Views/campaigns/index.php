<?php use App\Models\Campaign; ?>
<div class="page-head"><div><h1>Campaign</h1><p>Satu akun mengikuti satu campaign pada satu waktu.</p></div></div>
<?php if (!$campaigns): ?>
<section class="card narrow"><h2>Belum ada campaign</h2><p class="hint">Campaign yang dibuka admin akan muncul di sini.</p></section>
<?php else: ?>
<div class="cards">
  <?php foreach ($campaigns as $c): $state = Campaign::state($c); ?>
  <a class="card link-card" href="/campaign/<?= (int) $c['id'] ?>">
    <div class="tags">
      <span class="tag<?= $state === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($state)) ?></span>
      <?php if ($c['participation_id']): ?><span class="tag on">Diikuti</span><?php endif ?>
    </div>
    <h2><?= e($c['title']) ?></h2>
    <p class="clamp"><?= e($c['brief']) ?></p>
    <dl class="facts">
      <div><dt>Per 1.000 views</dt><dd><?= e(rupiah((int) $c['rate_per_1k'])) ?></dd></div>
      <div><dt>Maksimum</dt><dd><?= e(rupiah((int) $c['cap_per_account'])) ?></dd></div>
      <div><dt>Sampai</dt><dd><?= e(date('d/m/Y', strtotime($c['ends_at']))) ?></dd></div>
    </dl>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>
