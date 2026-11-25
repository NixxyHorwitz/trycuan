<?php /** Variabel: $account, $link, $campaign, $clicks, $week, $errors */ $installed = $link['link_verified_at'] !== null; ?>
<section class="card narrow gap" id="link">
  <div class="row">
    <h2 class="handle">Link bio</h2>
    <span class="tag<?= $installed ? ' good' : ($link['lost_at'] ? ' fail' : '') ?>"><?= $installed ? 'Terpasang' : ($link['lost_at'] ? 'Dilepas' : 'Belum terpasang') ?></span>
  </div>
  <?php if (!empty($errors['link'])): ?><div class="notice bad" role="alert"><?= icon('alert') ?><span><?= e($errors['link']) ?></span></div><?php endif ?>
  <div class="code"><output id="short"><?= e(App\Models\ShortLink::url($link)) ?></output><button class="btn outline" type="button" data-copy="short">Salin</button></div>
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
  <ul class="days">
    <?php $max = max(1, ...array_values($week)); foreach ($week as $day => $total): ?>
    <li><span><?= e(date('d/m', strtotime($day))) ?></span><progress max="<?= $max ?>" value="<?= $total ?>" aria-label="Klik <?= e($day) ?>"></progress><span><?= $total ?></span></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
