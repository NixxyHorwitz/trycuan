<?php /** Variabel: $c (campaign) */ $state = App\Models\Campaign::state($c); ?>
<a class="card link-card" href="/program/<?= (int) $c['id'] ?>">
  <div class="tags"><span class="tag<?= $state === 'open' ? ' good' : ' on' ?>"><?= e(App\Models\Campaign::label($state)) ?></span></div>
  <h3 class="mt-2 mb-4 text-base!"><?= e($c['title']) ?></h3>
  <div class="campaign-rate mt-auto"><div><strong><?= e(rupiah((int) $c['rate_per_1k'])) ?></strong><span>per 1.000 views</span></div><div class="text-right"><b class="block font-semibold tabular-nums"><?= e(rupiah((int) $c['cap_per_account'])) ?></b><span>maksimum</span></div></div>
  <p class="hint mt-3 flex items-center gap-1.5"><?= icon('calendar') ?>Sampai <?= e(date('d/m/Y', strtotime($c['ends_at']))) ?></p>
</a>
