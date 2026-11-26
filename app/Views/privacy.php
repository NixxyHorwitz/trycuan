<article class="card narrow doc">
  <h1>Privasi <?= e(setting('site_name')) ?></h1>
  <h2>Pengunjung link pendek</h2>
  <p>Kunjungan dicatat agar klik palsu tidak dibayar:</p>
  <ul class="rules">
    <li>Cookie acak untuk mengenali perangkat yang sama, berlaku satu tahun.</li>
    <li>Alamat IP dan ciri umum perangkat seperti ukuran layar, bahasa, dan zona waktu. Keduanya disimpan sebagai kode tersamar, bukan nilai aslinya.</li>
    <li>Waktu kunjungan, jenis perangkat, dan negara bila tersedia.</li>
  </ul>
  <p>Jejak perangkat dan jaringan pada catatan klik dihapus setelah 90 hari. Jumlah dan status klik tetap disimpan.</p>
  <h2>Kunjungan situs</h2>
  <p>Halaman yang dibuka, asal kunjungan, jenis perangkat, dan negara bila tersedia dicatat tanpa cookie pelacak. Pengenal pengunjung berupa kode yang berganti setiap hari. Catatan dihapus setelah 180 hari.</p>
  <h2>Pengguna terdaftar</h2>
  <ul class="rules">
    <li>Nama, email, dan password dalam bentuk hash. Bila masuk lewat Google: ID akun Google, nama, dan email.</li>
    <li>Username dan ID Threads, serta data publik utas yang kamu kirim: teks, waktu, views, dan suka.</li>
    <li>Perangkat yang dipakai masuk, untuk memisahkan klik milik sendiri.</li>
    <li>Rekening pencairan. Nomornya disimpan terenkripsi dan hanya dibaca admin untuk transfer.</li>
    <li>Mutasi saldo, penarikan, dan keputusan admin.</li>
  </ul>
  <h2>Pihak ketiga</h2>
  <p>Halaman link dapat memakai Cloudflare Turnstile untuk membedakan manusia dan bot. Data utas dan bio dibaca dari halaman publik Threads. Gambar avatar dimuat dari DiceBear memakai kode acak; nama dan email tidak dikirim.</p>
</article>
