<div class="page-head"><h1>Review utas</h1><p>Cocokkan isi dengan brief sebelum menyetujui.</p></div>
<div class="chips"><?php foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label): ?><a class="chip" href="/admin/utas<?= $key === 'pending' ? '' : "?status=$key" ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= (int) $counts[$key] ?></b></a><?php endforeach ?></div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<?php if (!$posts): ?><section class="empty"><?= icon('check') ?><h2><?= $tab === 'pending' ? 'Tidak ada yang menunggu' : 'Belum ada utas' ?></h2><p>Utas baru dari freelancer akan muncul di sini.</p></section><?php else: ?>
<div data-filter-group>
<div class="toolbar">
  <div class="search"><?= icon('search') ?><input type="search" data-filter-text placeholder="Saring akun, campaign, atau isi" aria-label="Saring utas"></div>
  <?php if ($tab === 'pending'): ?><label class="check items-center"><input type="checkbox" data-pick-all>Pilih semua</label><?php endif ?>
</div>
<div class="cards wide">
<?php foreach ($posts as $p): $id = (int) $p['id']; $error = $errors["alasan-$id"] ?? null; ?>
<section class="card flex h-full flex-col" data-item="<?= e($p['status']) ?>">
  <div class="row">
    <div class="entry"><?php if ($p['status'] === 'pending'): ?><input type="checkbox" data-pick name="ids[]" value="<?= $id ?>" form="bulk" aria-label="Pilih utas @<?= e($p['username']) ?>"><?php endif ?><?= avatar_of($p['user_id'], $p['username']) ?><div><h2><a class="title" href="/admin/user/<?= (int) $p['user_id'] ?>">@<?= e($p['username']) ?></a></h2><p class="hint"><?= e($p['title']) ?></p></div></div>
    <div class="tags"><span class="tag<?= ['approved' => ' good', 'rejected' => ' fail'][$p['status']] ?? ' wait' ?>"><?= ['pending' => 'Menunggu review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$p['status']] ?></span><?php if ($p['missing_at']): ?><span class="tag fail">Utas hilang</span><?php endif ?></div>
  </div>
  <p class="prose gap mb-auto"><?= nl2br(e($p['excerpt'] !== '' ? $p['excerpt'] : 'Utas tanpa teks')) ?></p>
  <p class="hint mt-4 flex flex-wrap items-center gap-x-3"><strong class="text-ink"><?= $p['views'] === null ? 'Views belum ada' : angka($p['views']) . ' views' ?></strong><?= $p['likes'] === null ? '' : '<span>' . angka($p['likes']) . ' suka</span>' ?><span><?= e(date('d/m/Y H:i', strtotime($p['posted_at']))) ?></span><a href="https://www.threads.com/@<?= e($p['username']) ?>/post/<?= e($p['post_code']) ?>" target="_blank" rel="noopener noreferrer">Buka di Threads</a></p>
  <?php if ($p['reject_reason']): ?><p class="hint">Alasan: <?= e($p['reject_reason']) ?></p><?php endif ?>
  <form class="mt-4 border-t border-line pt-4" method="post" action="/admin/utas/<?= $id ?>">
    <?= csrf_field() ?>
    <?php if ($p['status'] !== 'rejected'): ?>
    <div class="field">
      <label for="alasan-<?= $id ?>">Alasan bila ditolak</label>
      <input id="alasan-<?= $id ?>" name="alasan" type="text" maxlength="300" autocomplete="off" value="<?= e($old[$id] ?? '') ?>"<?= $error ? ' aria-invalid="true" aria-describedby="err-' . $id . '"' : '' ?>>
      <?php if ($error): ?><p class="error" id="err-<?= $id ?>"><?= e($error) ?></p><?php endif ?>
    </div>
    <?php endif ?>
    <div class="actions">
      <?php /* Tolak lebih dulu supaya Enter di kolom alasan tidak menyetujui. */ ?>
      <?php if ($p['status'] !== 'rejected'): ?><button class="btn danger" name="keputusan" value="tolak">Tolak</button><?php endif ?>
      <?php if ($p['status'] !== 'approved'): ?><button class="btn primary" name="keputusan" value="setujui">Setujui</button><?php endif ?>
    </div>
  </form>
</section>
<?php endforeach ?>
</div>
<p class="empty" data-none hidden>Tidak ada utas yang cocok.</p>
</div>
<?php if ($tab === 'pending'): ?>
<form class="bare bulk" id="bulk" method="post" action="/admin/utas">
  <?= csrf_field() ?>
  <strong data-picked>0 dipilih</strong>
  <input name="alasan" type="text" maxlength="300" autocomplete="off" placeholder="Alasan bila ditolak" aria-label="Alasan bila ditolak">
  <button class="btn danger" name="keputusan" value="tolak" data-confirm="Tolak semua utas yang dipilih?">Tolak</button>
  <button class="btn primary" name="keputusan" value="setujui" data-confirm="Setujui semua utas yang dipilih? Pastikan isinya sudah diperiksa.">Setujui</button>
</form>
<?php endif ?>
<?php endif ?>
