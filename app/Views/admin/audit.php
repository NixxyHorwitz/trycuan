<div class="page-head row"><div><h1>Audit</h1><p>Semua keputusan dan perubahan.</p></div><a class="btn outline" href="/admin/audit/ekspor" data-turbo="false"><?= icon('download') ?>Ekspor CSV</a></div>
<div class="chips"><a class="chip" href="/admin/audit"<?= $group === '' ? ' aria-current="true"' : '' ?>>Semua</a><?php foreach ($groups as $g): ?><a class="chip" href="/admin/audit?jenis=<?= e($g) ?>"<?= $group === $g ? ' aria-current="true"' : '' ?>><?= e($g) ?></a><?php endforeach ?></div>
<section class="card">
  <?php if (!$rows): ?><p class="hint">Tidak ada catatan.</p><?php endif ?>
  <ul class="list">
    <?php foreach ($rows as $r): ?>
    <li><div><strong><?= e($r['action']) ?></strong><p class="hint"><?= e(date('d/m/Y H:i:s', strtotime($r['created_at']))) ?>, <?= e($r['name'] ?? 'Sistem') ?>, <?= e($r['target']) ?></p>
      <?php if ($r['meta_json'] && $r['meta_json'] !== '[]'): ?><details><summary>Detail<?= icon('down') ?></summary><pre><?= e(json_encode(json_decode($r['meta_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif ?></div></li>
    <?php endforeach ?>
  </ul>
  <?php if (count($rows) === 50): ?><a class="btn outline gap" href="/admin/audit?sebelum=<?= (int) end($rows)['id'] ?><?= $group !== '' ? '&amp;jenis=' . e($group) : '' ?>">Lebih lama</a><?php endif ?>
</section>
