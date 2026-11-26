<?php /** Variabel: $account, $link, $campaign, $clicks, $week, $errors */ $installed = $link['link_verified_at'] !== null; ?>
<section class="card narrow gap" id="link">
  <div class="card-head">
    <h2>Link bio</h2>
    <span class="tag<?= $installed ? ' good' : ($link['lost_at'] ? ' fail' : '') ?>"><?= $installed ? 'Terpasang' : ($link['lost_at'] ? 'Dilepas' : 'Belum terpasang') ?></span>
  </div>
  <?php if (!empty($errors['link'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['link']) ?></span></div><?php endif ?>
  <div class="code"><output id="short"><?= e(App\Models\ShortLink::url($link)) ?></output><button class="btn outline" type="button" data-copy="short"><?= icon('copy') ?>Salin</button></div>
  <?php if ($installed): ?>
  <p class="hint">Terpasang sejak <?= e(date('d/m/Y H:i', strtotime($link['link_verified_at']))) ?> WIB. Hanya utas yang diposting setelah waktu ini yang diterima. Jangan lepas link selama campaign.</p>
  <?php else: ?>
  <p class="hint">Tempel di kolom link profil Threads, bukan di teks bio, lalu cek. Huruf besar dan kecil harus sama persis. Posting setelah link terdeteksi.</p>
  <?php endif ?>
  <form class="gap" method="post" action="/akun-threads/link" data-once="Memeriksa profil...">
    <?= csrf_field() ?>
    <button class="btn <?= $installed ? 'outline' : 'primary' ?>" type="submit">Cek link</button>
  </form>
</section>
<?php if ($campaign): ?>
<section class="card narrow gap">
  <div class="row"><h2 class="handle">Klik valid</h2><strong class="big"><?= number_format($clicks, 0, ',', '.') ?></strong></div>
  <p class="hint"><?= e($campaign['title']) ?>, 7 hari terakhir</p>
  <div class="chart mini gap"><canvas role="img" aria-label="Klik valid per hari: <?= e(implode(', ', $week)) ?>" <?= chart_attr(['labels' => array_map(static fn (string $d): string => date('d/m', strtotime($d)), array_keys($week)), 'type' => 'bar', 'sets' => [['label' => 'Klik valid', 'data' => array_values($week), 'color' => 'accent']]]) ?>></canvas></div>
</section>
<?php endif ?>
