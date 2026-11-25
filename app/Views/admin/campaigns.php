<?php use App\Models\Campaign; ?>
<div class="page-head"><a class="back" href="/admin">Admin</a><div class="row"><h1>Kelola campaign</h1><a class="btn primary" href="/admin/campaign/baru">Campaign baru</a></div></div>
<?php if (!$campaigns): ?>
<section class="card narrow"><h2>Belum ada campaign</h2><p class="hint">Buat campaign pertama supaya freelancer bisa ikut.</p></section>
<?php else: ?>
<div class="cards">
  <?php foreach ($campaigns as $c): $state = Campaign::state($c); ?>
  <a class="card link-card" href="/admin/campaign/<?= (int) $c['id'] ?>">
    <div class="tags"><span class="tag<?= $state === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($state)) ?></span></div>
    <h2><?= e($c['title']) ?></h2>
    <p class="hint"><?= e(date('d/m/Y H:i', strtotime($c['starts_at']))) ?> sampai <?= e(date('d/m/Y H:i', strtotime($c['ends_at']))) ?></p>
    <dl class="facts">
      <div><dt>Akun</dt><dd><?= (int) $c['accounts'] ?></dd></div>
      <div><dt>Utas</dt><dd><?= (int) $c['posts'] ?></dd></div>
      <div><dt>Anggaran</dt><dd><?= e(rupiah((int) $c['budget'])) ?></dd></div>
    </dl>
  </a>
  <?php endforeach ?>
</div>
<?php endif ?>
