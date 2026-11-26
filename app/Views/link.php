<section class="card go-card">
  <span class="mark"><?= icon('trend') ?></span>
  <?php if ($campaigns): ?>
  <h1>Pilih tujuan</h1>
  <?php foreach ($campaigns as $c): ?><a class="btn outline block" href="/<?= e($code) ?>?c=<?= (int) $c['id'] ?>" rel="nofollow"><?= e($c['title']) ?></a><?php endforeach ?>
  <?php elseif (!$campaign): ?>
  <h1>Link belum aktif</h1>
  <p class="hint">Belum ada tujuan untuk link ini. Coba lagi nanti.</p>
  <?php else: ?>
  <h1><?= e($campaign['title']) ?></h1>
  <p class="hint">Kamu akan menuju <strong><?= e((string) parse_url($campaign['destination_url'], PHP_URL_HOST)) ?></strong></p>
  <div id="challenge" data-sitekey="<?= e($siteKey) ?>"></div>
  <a class="btn primary block" id="go" href="<?= e($campaign['destination_url']) ?>" rel="nofollow noreferrer" data-code="<?= e($code) ?>" data-token="<?= e($token) ?>">Lanjut</a>
  <p class="hint">Kami mencatat kunjungan secara tersamar untuk mencegah klik palsu. <a href="/privasi">Privasi</a></p>
  <?php if ($siteKey !== ''): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" defer></script><?php endif ?>
  <script src="<?= e(asset('link.js')) ?>" defer></script>
  <?php endif ?>
</section>
