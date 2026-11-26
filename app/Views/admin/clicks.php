<div class="page-head"><h1>Log klik</h1><p>100 klik terakhir yang punya token sah.</p></div>
<section class="card">
  <h2>Lapis pihak ketiga</h2>
  <div class="tags"><?php foreach ($layers as $name => $on): ?><span class="tag<?= $on ? ' good' : ' fail' ?>"><?= e($name) ?>: <?= $on ? 'aktif' : 'nonaktif' ?></span><?php endforeach ?></div>
  <p class="hint mt-3">Tanpa Turnstile, semua klik yang lolos lapis lain ditahan. Tanpa Cloudflare, negara tidak diperiksa.</p>
</section>
<div class="chips gap"><?php foreach (['' => 'Semua', 'valid' => 'Valid', 'held' => 'Ditahan', 'invalid' => 'Tidak valid'] as $key => $label): ?><a class="chip" href="/admin/klik<?= $key === '' ? '' : "?status=$key" ?>"<?= $status === $key ? ' aria-current="true"' : '' ?>><?= $label ?></a><?php endforeach ?></div>
<?php if (!$clicks): ?><section class="empty"><?= icon('click') ?><p>Belum ada klik.</p></section><?php else: ?>
<section class="card py-0!"><div class="table-wrap"><table class="fold"><thead><tr><th>Akun</th><th>Waktu</th><th>Perangkat</th><th>Alasan</th><th class="right">Risiko</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($clicks as $c): $reasons = json_decode($c['reasons_json'], true); ?><tr>
    <td><strong class="font-medium">@<?= e($c['username']) ?></strong></td>
    <td data-label="Waktu" class="whitespace-nowrap"><?= e(date('d/m/Y H:i:s', strtotime($c['created_at']))) ?></td>
    <td data-label="Perangkat"><?= e($c['device_type']) ?><?= $c['country'] ? ', ' . e($c['country']) : '' ?></td>
    <td data-label="Alasan" class="text-soft"><?= $reasons ? e(str_replace('_', ' ', implode(', ', $reasons))) : '-' ?></td>
    <td class="right" data-label="Risiko"><?= (int) $c['risk_score'] ?></td>
    <td data-label="Status"><span class="tag<?= ['valid' => ' good', 'invalid' => ' fail'][$c['status']] ?? ' wait' ?>"><?= ['valid' => 'Valid', 'held' => 'Ditahan', 'invalid' => 'Tidak valid'][$c['status']] ?></span></td>
  </tr><?php endforeach ?>
</tbody></table></div></section>
<?php endif ?>
