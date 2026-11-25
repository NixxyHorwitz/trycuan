<section class="hero">
  <div>
    <h1>Posting utas, dibayar dari views</h1>
    <p class="lead">Ikuti campaign, posting di akun Threads kamu, lalu pantau fee dari total views semua utas yang disetujui.</p>
    <div class="actions">
      <a class="btn primary large" href="<?= $user ? '/dashboard' : '/daftar' ?>"><?= $user ? 'Buka dashboard' : 'Buat akun' ?><?= icon('arrow') ?></a>
    </div>
  </div>
  <div class="card calc">
    <label for="views">Total views akun</label>
    <input id="views" type="number" inputmode="numeric" min="0" max="10000000" step="100" value="12480">
    <div class="meter"><progress id="meter" max="1000" value="480" aria-label="Progres ke kelipatan berikutnya"></progress></div>
    <p class="hint" id="next">520 views lagi untuk +Rp10.000</p>
    <div class="total"><span>Estimasi fee</span><output id="fee" for="views">Rp120.000</output></div>
    <p class="hint">Rp10.000 per kelipatan 1.000 views. Batas fee dapat berlaku sesuai campaign.</p>
  </div>
</section>
<section>
  <h2>Cara kerja</h2>
  <ol class="steps">
    <li><?= icon('user') ?><h3>Verifikasi akun</h3><p>Tempel kode di bio Threads untuk membuktikan akun itu milikmu.</p></li>
    <li><?= icon('link') ?><h3>Pasang link</h3><p>Pasang shortlink pribadi di profil sebelum mulai posting.</p></li>
    <li><?= icon('edit') ?><h3>Posting utas</h3><p>Ikuti syarat campaign, lalu kirim URL utas untuk diperiksa.</p></li>
    <li><?= icon('wallet') ?><h3>Terima fee</h3><p>Fee cair setelah views dan kualitas klik akun lolos pemeriksaan.</p></li>
  </ol>
</section>
