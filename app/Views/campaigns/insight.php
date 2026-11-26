<?php $id = (int) $campaign['id']; ?>
<div class="page-head">
  <a class="back" href="/campaign/<?= $id ?>"><?= icon('back') ?><?= e($campaign['title']) ?></a>
  <h1>Setor views</h1>
  <p>Untuk utas yang views-nya belum terbaca atau sudah bertambah.</p>
</div>
<?php if (!empty($errors['form'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div><?php endif ?>
<div class="split">
  <section class="card">
    <?php if (!$open): ?><p class="hint">Campaign ini tidak sedang menerima setoran views.</p>
    <?php else: ?>
    <form method="post" action="/campaign/<?= $id ?>/setor" enctype="multipart/form-data" data-once="Mengirim...">
      <?= csrf_field() ?>
      <?php (function () use ($errors, $old) { $key = 'url'; $label = 'Link utas'; $type = 'text'; $value = $old['url']; $error = $errors['url'] ?? null; $hint = 'Link dari titik tiga atau tombol bagikan juga bisa.'; $attrs = App\Services\Threads\PostLink::FIELD; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <?php (function () use ($errors, $old) { $key = 'views'; $label = 'Jumlah views di insight'; $type = 'text'; $value = $old['views']; $error = $errors['views'] ?? null; $hint = 'Isi angka lengkap sesuai screenshot.'; $attrs = 'data-money data-plain inputmode="numeric" maxlength="15" data-minimum="1" data-maximum="' . App\Services\ViewReports::MAX_VIEWS . '"'; require ROOT . '/app/Views/partials/field.php'; })() ?>
      <div class="field"><label for="bukti">Screenshot insight</label><div class="file"><input id="bukti" name="bukti" type="file" data-max="<?= App\Services\ViewReports::MAX_FILE ?>" accept="image/jpeg,image/png,image/webp" required<?= !empty($errors['bukti']) ? ' aria-invalid="true" aria-describedby="bukti-error"' : '' ?>><?= icon('upload') ?><span><strong data-file="Pilih atau seret gambar">Pilih atau seret gambar</strong><span class="hint">JPG, PNG, atau WebP, maksimal 5 MB.</span></span></div>
      <?php if (!empty($errors['bukti'])): ?><p class="error" id="bukti-error"><?= e($errors['bukti']) ?></p><?php endif ?></div>
      <button class="btn primary" type="submit">Kirim setoran</button>
    </form>
    <?php endif ?>
  </section>
  <section class="card">
    <h2>Syarat screenshot</h2>
    <ul class="rules">
      <li>Buka utas di Threads, lalu buka insight-nya.</li>
      <li>Jumlah views dan utasnya terlihat jelas, tidak dipotong atau diedit.</li>
      <li>Ambil screenshot baru setiap kali menyetor.</li>
      <li>Setoran bisa dikirim sampai 24 jam setelah campaign selesai.</li>
    </ul>
    <p class="hint gap">Bila Threads nanti menampilkan views utas, angka dari Threads yang dipakai.</p>
  </section>
</div>
<section class="card gap">
  <div class="card-head"><h2>Riwayat setoran</h2></div>
  <?php if (!$reports): ?><p class="hint">Belum ada setoran.</p><?php endif ?>
  <ul class="list"><?php foreach ($reports as $r): ?>
    <li><div><strong class="title"><?= e($r['excerpt'] !== '' ? $r['excerpt'] : 'Utas tanpa teks') ?></strong>
      <p class="hint"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?> WIB. <a href="/insight/<?= (int) $r['id'] ?>/bukti" target="_blank" rel="noopener">Lihat screenshot</a><?= $r['reason'] ? '. Alasan: ' . e($r['reason']) : '' ?></p></div>
      <div class="meta"><strong><?= angka($r['approved_views'] ?? $r['claimed_views']) ?> views</strong><span class="tag<?= ['approved' => ' good', 'rejected' => ' fail'][$r['status']] ?? ' wait' ?>"><?= ['pending' => 'Ditinjau', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$r['status']] ?></span></div></li>
  <?php endforeach ?></ul>
</section>
