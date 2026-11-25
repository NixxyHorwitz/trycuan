<div class="page-head"><a class="back" href="/admin">Admin</a><h1>Log klik</h1><p>100 klik terakhir yang punya token sah.</p></div>
<section class="card">
  <h2>Lapis pihak ketiga</h2>
  <div class="tags"><?php foreach ($layers as $name => $on): ?><span class="tag<?= $on ? ' good' : ' fail' ?>"><?= e($name) ?>: <?= $on ? 'aktif' : 'nonaktif' ?></span><?php endforeach ?></div>
  <p class="hint">Tanpa Turnstile, semua klik yang lolos lapis lain ditahan. Tanpa Cloudflare, negara tidak diperiksa.</p>
</section>
<section class="card gap">
  <?php if (!$clicks): ?><p class="hint">Belum ada klik.</p><?php endif ?>
  <ul class="posts">
    <?php foreach ($clicks as $c): $reasons = json_decode($c['reasons_json'], true); ?>
    <li>
      <div class="post-text"><strong>@<?= e($c['username']) ?></strong><p class="hint"><?= e(date('d/m/Y H:i:s', strtotime($c['created_at']))) ?>, <?= e($c['device_type']) ?><?= $c['country'] ? ', ' . e($c['country']) : '' ?><?php if ($reasons): ?>. <?= e(str_replace('_', ' ', implode(', ', $reasons))) ?><?php endif ?></p></div>
      <div class="post-meta"><span>Risiko <?= (int) $c['risk_score'] ?></span><span class="tag<?= ['valid' => ' good', 'invalid' => ' fail'][$c['status']] ?? '' ?>"><?= ['valid' => 'Valid', 'held' => 'Ditahan', 'invalid' => 'Tidak valid'][$c['status']] ?></span></div>
    </li>
    <?php endforeach ?>
  </ul>
</section>
