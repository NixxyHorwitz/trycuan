<?php use App\Models\Campaign; ?>
<div class="page-head row"><div><h1>Campaign</h1><p><?= count($campaigns) ?> campaign</p></div><a class="btn primary" href="/admin/campaign/baru"><?= icon('plus') ?>Campaign baru</a></div>
<?php if (!$campaigns): ?>
<section class="empty"><?= icon('campaign') ?><h2>Belum ada campaign</h2><p>Buat campaign pertama supaya freelancer bisa ikut.</p><a class="btn primary" href="/admin/campaign/baru">Buat campaign</a></section>
<?php else: ?>
<div data-filter-group>
  <div class="toolbar"><div class="search"><?= icon('search') ?><input type="search" data-filter-text placeholder="Cari campaign" aria-label="Cari campaign"></div></div>
  <div class="chips"><?php foreach (['' => 'Semua', 'open' => 'Berjalan', 'upcoming' => 'Segera mulai', 'paused' => 'Dijeda', 'draft' => 'Draf', 'ended' => 'Selesai'] as $key => $label): ?><button class="chip" type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key === '' ? 'true' : 'false' ?>"><?= $label ?></button><?php endforeach ?></div>
  <div class="cards">
    <?php foreach ($campaigns as $c): $state = Campaign::state($c); ?>
    <a class="card link-card" href="/admin/campaign/<?= (int) $c['id'] ?>/ringkasan" data-item="<?= e($state) ?>">
      <div class="tags"><span class="tag<?= ['open' => ' good', 'paused' => ' wait', 'upcoming' => ' on'][$state] ?? '' ?>"><?= e(Campaign::label($state)) ?></span></div>
      <h2><?= e($c['title']) ?></h2>
      <p class="hint mt-1 mb-4"><?= e(date('d/m/Y', strtotime($c['starts_at']))) ?> sampai <?= e(date('d/m/Y', strtotime($c['ends_at']))) ?></p>
      <dl class="facts mt-auto">
        <div><dt>Akun</dt><dd><?= (int) $c['accounts'] ?></dd></div>
        <div><dt>Utas</dt><dd><?= (int) $c['posts'] ?></dd></div>
        <div><dt>Anggaran</dt><dd><?= e(rupiah((int) $c['budget'])) ?></dd></div>
      </dl>
    </a>
    <?php endforeach ?>
  </div>
  <p class="empty" data-none hidden>Tidak ada campaign yang cocok.</p>
</div>
<?php endif ?>
