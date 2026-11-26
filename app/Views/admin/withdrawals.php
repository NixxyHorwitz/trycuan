<?php use App\Core\Crypto; use App\Models\PayoutMethod; ?>
<div class="page-head row"><div><h1>Penarikan</h1><p>Transfer manual dulu, lalu unggah buktinya.</p></div><a class="btn outline" href="/admin/penarikan/ekspor" data-turbo="false"><?= icon('download') ?>Ekspor CSV</a></div>
<div class="chips"><?php foreach (['pending' => 'Menunggu', 'paid' => 'Dibayar', 'rejected' => 'Ditolak'] as $key => $label): ?><a class="chip" href="/admin/penarikan<?= $key === 'pending' ? '' : "?status=$key" ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= $label ?> <b><?= (int) $counts[$key] ?></b></a><?php endforeach ?></div>
<?php if (!$rows): ?><section class="empty"><?= icon('bank') ?><h2>Tidak ada penarikan</h2><p>Permintaan dari freelancer akan muncul di sini.</p></section><?php endif ?>
<div class="cards wide">
<?php foreach ($rows as $w): $id = (int) $w['id']; ?>
<section class="card">
  <div class="tags">
    <span class="tag<?= ['paid' => ' good', 'rejected' => ' fail'][$w['status']] ?? ' wait' ?>"><?= ['pending' => 'Menunggu', 'paid' => 'Dibayar', 'rejected' => 'Ditolak'][$w['status']] ?></span>
    <?php if ($w['shared']): ?><span class="tag fail">Rekening dipakai user lain</span><?php endif ?>
    <?php if ($w['user_status'] === 'blocked'): ?><span class="tag fail">User diblokir</span><?php endif ?>
  </div>
  <p class="amount"><?= e(rupiah((int) $w['amount'])) ?></p>
  <p><strong><?= e($w['name']) ?></strong><?= $w['username'] ? ' @' . e($w['username']) : '' ?></p>
  <p class="hint">Diajukan <?= e(date('d/m/Y H:i', strtotime($w['created_at']))) ?> WIB</p>
  <dl class="facts gap">
    <div><dt>Tujuan</dt><dd><?= e(PayoutMethod::PROVIDERS[$w['provider']] ?? $w['provider']) ?></dd></div>
    <div><dt>Nomor</dt><dd><output id="rek-<?= $id ?>"><?= e($w['status'] === 'pending' ? Crypto::decrypt($w['account_enc']) : PayoutMethod::masked($w)) ?></output><?php if ($w['status'] === 'pending'): ?> <button class="hint underline" type="button" data-copy="rek-<?= $id ?>">Salin</button><?php endif ?></dd></div>
    <div><dt>Nama</dt><dd><?= e($w['account_name']) ?></dd></div>
  </dl>
  <?php if (isset($errors[$id])): ?><p class="error gap" role="alert"><?= e($errors[$id]) ?></p><?php endif ?>
  <?php if ($w['status'] === 'pending'): ?>
  <form class="mt-4 border-t border-line pt-4" method="post" action="/admin/penarikan/<?= $id ?>" enctype="multipart/form-data" data-once="Menyimpan..." data-confirm="Tandai <?= e(rupiah((int) $w['amount'])) ?> sudah ditransfer? Ini tidak bisa dibatalkan.">
    <?= csrf_field() ?>
    <div class="field"><label for="bukti-<?= $id ?>">Bukti transfer</label><div class="file"><input id="bukti-<?= $id ?>" name="bukti" type="file" data-max="2097152" accept="image/jpeg,image/png,image/webp,application/pdf" required><?= icon('upload') ?><span><strong data-file="Pilih atau seret berkas">Pilih atau seret berkas</strong><span class="hint">JPG, PNG, WebP, atau PDF, maksimal 2 MB.</span></span></div></div>
    <input type="hidden" name="keputusan" value="bayar">
    <button class="btn primary" type="submit">Tandai dibayar</button>
  </form>
  <details class="mt-2"><summary>Tolak penarikan<?= icon('down') ?></summary><form method="post" action="/admin/penarikan/<?= $id ?>" data-confirm="Tolak penarikan ini dan kembalikan saldonya?">
    <?= csrf_field() ?>
    <input type="hidden" name="keputusan" value="tolak">
    <div class="field"><label for="tolak-<?= $id ?>">Alasan penolakan</label><input id="tolak-<?= $id ?>" name="alasan" type="text" minlength="3" maxlength="300" autocomplete="off" required></div>
    <button class="btn danger" type="submit">Tolak dan kembalikan saldo</button>
  </form></details>
  <?php elseif ($w['proof_file']): ?><p class="gap"><a href="/admin/penarikan/<?= $id ?>/bukti" target="_blank" rel="noopener">Lihat bukti</a></p>
  <?php elseif ($w['reason']): ?><p class="hint gap">Alasan: <?= e($w['reason']) ?></p><?php endif ?>
</section>
<?php endforeach ?>
</div>
