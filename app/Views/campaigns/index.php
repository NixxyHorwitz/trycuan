<?php use App\Models\Campaign; ?>
<div class="page-head"><h1>Campaign</h1><p><?= ['multi' => 'Kamu boleh mengikuti beberapa campaign sekaligus.', 'focus' => 'Campaign utama yang sedang berjalan.'][setting('campaign_mode')] ?? 'Satu akun mengikuti satu campaign pada satu waktu.' ?></p></div>
<?php if (!$campaigns): ?>
<section class="empty"><?= icon('campaign') ?><h2>Belum ada campaign</h2><p>Campaign yang dibuka akan muncul di sini.</p></section>
<?php else: ?>
<div data-filter-group>
  <div class="toolbar"><div class="search"><?= icon('search') ?><input type="search" data-filter-text placeholder="Cari campaign" aria-label="Cari campaign"></div></div>
  <div class="chips">
    <?php foreach (['' => 'Semua', 'mine' => 'Diikuti', 'open' => 'Berjalan', 'upcoming' => 'Segera mulai', 'ended' => 'Selesai'] as $key => $label): ?>
    <button class="chip" type="button" data-filter="<?= $key ?>" aria-pressed="<?= $key === '' ? 'true' : 'false' ?>"><?= $label ?></button>
    <?php endforeach ?>
  </div>
  <div class="cards">
    <?php foreach ($campaigns as $c): $state = Campaign::state($c); ?>
    <a class="card link-card" href="/campaign/<?= (int) $c['id'] ?>" data-item="<?= e($state) ?><?= $c['participation_id'] ? ' mine' : '' ?>">
      <div class="tags">
        <span class="tag<?= $state === 'open' ? ' good' : '' ?>"><?= e(Campaign::label($state)) ?></span>
        <?php if ($c['docs']): ?><span class="tag"><?= (int) $c['docs'] ?> dokumen</span><?php endif ?><?php if ($c['participation_id']): ?><span class="tag on">Diikuti</span><?php endif ?>
      </div>
      <h2><?= e($c['title']) ?></h2>
      <p class="mt-1 mb-auto line-clamp-2 text-soft"><?= e($c['brief']) ?></p>
      <div class="campaign-rate"><div><strong><?= e(rupiah((int) $c['rate_per_1k'])) ?></strong><span>per 1.000 views</span></div><div class="text-right"><b class="block font-semibold tabular-nums"><?= e(rupiah((int) $c['cap_per_account'])) ?></b><span>maksimum</span></div></div>
      <p class="hint mt-3 flex items-center gap-1.5"><?= icon('calendar') ?>Sampai <?= e(date('d/m/Y', strtotime($c['ends_at']))) ?></p>
    </a>
    <?php endforeach ?>
  </div>
  <div class="empty" data-none hidden><?= icon('search') ?><p>Tidak ada campaign yang cocok.</p></div>
</div>
<?php endif ?>
