<?php /** Variabel: $p (utas), $username, opsional $reportTo (ID campaign yang menerima setoran views) */ ?>
<li data-item="<?= e($p['status']) ?>">
  <div>
    <a class="title" href="https://www.threads.com/@<?= e($username) ?>/post/<?= e($p['post_code']) ?>" target="_blank" rel="noopener noreferrer"><?= e($p['excerpt'] !== '' ? $p['excerpt'] : 'Utas tanpa teks') ?></a>
    <p class="hint">Diposting <?= e(date('d/m/Y H:i', strtotime($p['posted_at']))) ?> WIB<?php if ($p['reject_reason']): ?>. Alasan: <?= e($p['reject_reason']) ?><?php endif ?></p>
    <?php if ($p['last_error'] && $p['status'] !== 'rejected'): ?><p class="error"><?= $p['missing_at'] ? 'Tidak dihitung: ' : 'Gagal diperbarui: ' ?><?= e($p['last_error']) ?></p><?php endif ?>
  </div>
  <div class="meta">
    <strong><?= $p['views'] === null ? 'Views belum ada' : number_format((int) $p['views'], 0, ',', '.') . ' views' ?></strong>
    <?php if (!empty($p['reported'])): ?><span class="tag wait">Setoran ditinjau</span><?php elseif (!empty($reportTo) && $p['status'] !== 'rejected'): ?><a class="text-btn" href="/campaign/<?= (int) $reportTo ?>/setor?utas=<?= (int) $p['id'] ?>"><?= icon('upload') ?>Setor views</a><?php endif ?>
    <span class="tag<?= ['approved' => ' good', 'rejected' => ' fail'][$p['status']] ?? ' wait' ?>"><?= ['pending' => 'Menunggu review', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$p['status']] ?></span>
    <?php if (!empty($refreshRow) && $p['status'] !== 'rejected'): ?><button class="icon-btn" type="button" data-refresh-post="<?= (int) $p['id'] ?>" aria-label="Perbarui utas ini"><?= icon('refresh') ?></button><?php endif ?>
  </div>
</li>
