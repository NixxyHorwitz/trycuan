<?php $id = (int) $campaign['id']; $state = App\Models\Campaign::state($campaign); ?>
<div class="page-head"><a class="back" href="/program"><?= icon('back') ?>Semua campaign</a><div class="row"><h1><?= e($campaign['title']) ?></h1><span class="tag<?= $state === 'open' ? ' good' : ' on' ?>"><?= e(App\Models\Campaign::label($state)) ?></span></div></div>
<dl class="facts card">
  <div><dt>Per 1.000 views</dt><dd><?= e(rupiah((int) $campaign['rate_per_1k'])) ?></dd></div>
  <div><dt>Maksimum per akun</dt><dd><?= e(rupiah((int) $campaign['cap_per_account'])) ?></dd></div>
  <div><dt>Mulai</dt><dd><?= e(date('d/m/Y', strtotime($campaign['starts_at']))) ?></dd></div>
  <div><dt>Selesai</dt><dd><?= e(date('d/m/Y', strtotime($campaign['ends_at']))) ?></dd></div>
</dl>
<section class="card"><h2>Ikut campaign ini</h2><p class="text-soft">Brief, syarat, dan materi lengkap terbuka setelah kamu masuk.</p><div class="actions mt-4"><a class="btn primary" href="/campaign/<?= $id ?>"><?= $user ? 'Buka campaign' : 'Masuk untuk ikut' ?></a><?php if (!$user && App\Models\Setting::int('register_open')): ?><a class="btn outline" href="/daftar">Daftar</a><?php endif ?></div></section>
