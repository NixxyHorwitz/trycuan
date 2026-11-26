<div class="page-head"><h1>Setoran views</h1><p>Cocokkan screenshot insight dengan utasnya sebelum menyetujui.</p></div>
<div class="chips"><?php foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label): ?><a class="chip" href="/admin/insight<?= $key === 'pending' ? '' : "?status=$key" ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= (int) $counts[$key] ?></b></a><?php endforeach ?></div>
<?php if (!$rows): ?><section class="empty"><?= icon('eye') ?><h2>Tidak ada setoran</h2><p>Screenshot insight dari freelancer akan muncul di sini.</p></section><?php endif ?>
<div class="cards wide">
<?php foreach ($rows as $r): $id = (int) $r['id']; ?>
<section class="card flex h-full flex-col">
  <div class="row">
    <div class="entry"><?= avatar_of($r['user_id'], $r['username']) ?><div><h2><a class="title" href="/admin/akun/<?= (int) $r['participation_id'] ?>">@<?= e($r['username']) ?></a></h2><p class="hint"><?= e($r['title']) ?></p></div></div>
    <div class="tags"><span class="tag<?= ['approved' => ' good', 'rejected' => ' fail'][$r['status']] ?? ' wait' ?>"><?= ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$r['status']] ?></span><?php if ($r['missing_at']): ?><span class="tag fail">Utas hilang</span><?php endif ?><?php if ($r['post_status'] !== 'approved'): ?><span class="tag"><?= $r['post_status'] === 'pending' ? 'Utas belum direview' : 'Utas ditolak' ?></span><?php endif ?></div>
  </div>
  <a class="shot gap" href="/admin/insight/<?= $id ?>/bukti" target="_blank" rel="noopener"><img src="/admin/insight/<?= $id ?>/bukti" alt="Screenshot insight dari @<?= e($r['username']) ?>" loading="lazy"></a>
  <p class="prose gap mb-auto"><?= nl2br(e($r['excerpt'] !== '' ? $r['excerpt'] : 'Utas tanpa teks')) ?></p>
  <dl class="facts gap">
    <div><dt>Disetor</dt><dd><?= angka($r['claimed_views']) ?></dd></div>
    <div><dt>Tercatat</dt><dd><?= $r['views'] === null ? 'Belum ada' : angka($r['views']) . ($r['view_source'] === 'insight' ? ' (insight)' : ' (Threads)') ?></dd></div>
    <?php if ($r['approved_views'] !== null): ?><div><dt>Disetujui</dt><dd><?= angka($r['approved_views']) ?></dd></div><?php endif ?>
    <div><dt>Dikirim</dt><dd><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></dd></div>
  </dl>
  <p class="hint gap"><a href="https://www.threads.com/@<?= e($r['username']) ?>/post/<?= e($r['post_code']) ?>" target="_blank" rel="noopener noreferrer">Buka di Threads</a><?= $r['reason'] ? '. Alasan: ' . e($r['reason']) : '' ?></p>
  <?php if (isset($errors[$id])): ?><p class="error gap" role="alert"><?= e($errors[$id]) ?></p><?php endif ?>
  <?php if ($r['status'] === 'pending'): ?>
  <form class="mt-4 border-t border-line pt-4" method="post" action="/admin/insight/<?= $id ?>" data-once="Menyimpan...">
    <?= csrf_field() ?>
    <input type="hidden" name="keputusan" value="setujui">
    <div class="field"><label for="views-<?= $id ?>">Views sesuai screenshot</label><input id="views-<?= $id ?>" name="views" type="text" inputmode="numeric" data-money data-plain data-minimum="1" data-maximum="<?= App\Services\ViewReports::MAX_VIEWS ?>" autocomplete="off" required value="<?= (int) $r['claimed_views'] ?>"></div>
    <button class="btn primary" type="submit"<?= $r['post_status'] === 'rejected' ? ' disabled' : '' ?>>Setujui views</button>
  </form>
  <?php endif ?>
  <?php if ($r['status'] !== 'rejected'): ?>
  <details class="mt-2"><summary><?= $r['status'] === 'approved' ? 'Batalkan persetujuan' : 'Tolak setoran' ?><?= icon('down') ?></summary><form method="post" action="/admin/insight/<?= $id ?>"<?= $r['status'] === 'approved' ? ' data-confirm="Batalkan views ini? Fee yang sudah masuk saldo tidak ditarik."' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="keputusan" value="tolak">
    <div class="field"><label for="tolak-<?= $id ?>">Alasan</label><input id="tolak-<?= $id ?>" name="alasan" type="text" minlength="3" maxlength="300" autocomplete="off" required></div>
    <button class="btn danger" type="submit"><?= $r['status'] === 'approved' ? 'Batalkan' : 'Tolak' ?></button>
  </form></details>
  <?php endif ?>
</section>
<?php endforeach ?>
</div>
