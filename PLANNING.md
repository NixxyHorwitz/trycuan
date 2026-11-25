# TryCuan: Planning

Platform tempat freelancer memposting utas Threads sesuai syarat campaign, lalu dibayar berdasarkan **total views semua utas terverifikasi di akunnya**: Rp10.000 per kelipatan 1.000 views.

Nilai bertanda **[usulan]** belum ditentukan pemilik produk dan bisa diubah dari panel admin.

## 1. Hasil uji kelayakan (25 Nov 2026)

Diuji dari device ini dengan PHP 8.3 + cURL, tanpa login Threads.

| Uji | Hasil | Dampak ke desain |
|---|---|---|
| Views utas | `"view_counts":675037` ada di HTML, angka persis | Tidak perlu headless browser atau parsing "675K" |
| Pemilik utas | JSON memuat `username`, `pk` (ID akun), `taken_at`, caption | Verifikasi kepemilikan dan syarat bisa otomatis |
| URL dengan username palsu | Threads mengalihkan ke pemilik asli | Pemilik **wajib** dibaca dari JSON, bukan dari URL input |
| Utas tidak ada | HTTP 200, dialihkan ke `/?error=invalid_post`, views `null` | HTTP 200 bukan tanda sukses; `null` tidak boleh menjadi 0 |
| Bio profil | Field `biography` ada | Verifikasi kode di bio bisa dilakukan |
| Link bio | `bio_links[].url` terbaca di 3 profil; `[]` di profil tanpa link | Pemasangan shortlink bisa dicek otomatis |
| Akun tidak ada | HTTP 200, dialihkan ke halaman login | Dianggap gagal dibaca, bukan "kode tidak ada" |
| Durasi | 1,0 sampai 2,3 detik, sekitar 0,9 sampai 1 MB per halaman | Refresh via AJAX per card supaya halaman tidak tertahan |

Uji tahap 2: 21 request berurutan ke Threads tanpa blokir; views utas kecil (2 sampai 529) dan balasan terbaca. Balasan punya views sendiri dan ditandai `isReply`, sehingga submit harus menolaknya.

Belum diuji: batas request per IP, akun privat, utas dengan views disembunyikan, dan akses dari IP VPS. IP hosting bisa diperlakukan berbeda dari IP rumahan, jadi perlu uji ulang sebelum online.

**Risiko scraping:** struktur HTML bisa berubah sewaktu-waktu, IP bisa diblokir, dan pengambilan data otomatis tanpa izin berpotensi melanggar ketentuan Meta. Karena itu scraper dibuat di balik interface `ThreadsSource`, sehingga nanti dapat diganti API resmi Threads (butuh izin OAuth tiap user) tanpa mengubah bagian lain.

## 2. Stack

- PHP 8.3 native, MVC ringan, PDO, autoload sendiri, tes PHP CLI (tanpa Composer)
- MySQL 8.4, Apache 2.4 (document root ke `public/`)
- CSS sendiri dan JavaScript vanilla, tanpa build step
- Ikon SVG Lucide disimpan lokal; tidak ada emoji sebagai ikon
- URL lokal: `http://trycuan.test`

Alasan native: alurnya kecil dan mudah dipasang di shared hosting. Konsekuensinya, CSRF, session, dan rate limit harus ditulis dan dites sendiri.

## 3. Peran dan alur

**Freelancer:** daftar → verifikasi akun Threads → pilih campaign → pasang shortlink di bio → posting di Threads → submit URL → pantau card akun → tarik saldo.

**Admin:** kelola campaign dan syarat, review utas dan akun, proses penarikan, blokir user, pantau kesehatan scraper.

### 3.1 Verifikasi akun Threads
1. User mengisi username. Sistem membuat kode acak, misalnya `TC-7K4M9Q2X`, berlaku 30 menit [usulan].
2. User menempel kode di bio, lalu menekan Verifikasi.
3. Sistem mengambil profil dan mencocokkan kode di `biography` milik username yang sama.
4. Bila cocok, simpan `threads_pk` dan username. Kode langsung hangus dan boleh dihapus dari bio.

Aturan: satu `pk` hanya untuk satu user (unique di DB). Kepemilikan memakai `pk` karena username bisa berganti; username disimpan untuk tampilan.

### 3.2 Submit dan verifikasi utas
1. Terima hanya `https://(www.)threads.com|threads.net/@user/post/{code}`. Ambil `code`-nya, lalu server menyusun URL sendiri (mencegah SSRF).
2. Ambil halaman dan cari node JSON yang `code`-nya **persis sama**, agar data balasan atau post rekomendasi tidak terbaca.
3. Cek: bukan balasan, `pk` pemilik sama dengan akun terverifikasi, waktu post dalam periode campaign, `code` belum pernah disubmit.
4. Cek syarat otomatis: kata/topik/mention wajib, kata terlarang, panjang minimum. Topik Threads ada di `tag_header`, di luar caption, jadi keduanya dibaca.
5. Syarat kualitatif dicek admin. Status: `pending → approved / rejected`.

Yang dihitung hanya views **post utama** dari URL yang disubmit, bukan jumlah semua post dalam utas [usulan].

### 3.3 Update views
Hanya dipicu aksi user, tanpa cron:
- **Masuk web/dashboard:** utas dengan data lebih tua dari 10 menit [usulan] diperbarui satu per satu via AJAX.
- **Refresh card akun:** memperbarui semua utas akun secara berurutan dengan progres (misalnya 3/8). Cooldown 60 detik per akun [usulan], dicek di server. Tiap baris utas juga bisa direfresh sendiri.
- **Ajukan penarikan:** semua utas yang dihitung wajib dibaca ulang.

Gagal baca: pertahankan angka lama, tandai utasnya "Gagal diperbarui", dan tandai total akun "Sebagian belum terbaru". Circuit breaker menghentikan request sementara setelah banyak kegagalan beruntun.

### 3.4 Rumus fee per akun
Satuan hitung adalah **partisipasi**: satu akun Threads di satu campaign. Views campaign lama tidak terbawa ke campaign baru karena anggarannya berbeda.

```
total_views = jumlah views utas berstatus approved milik partisipasi
fee_total   = min( floor(total_views / 1000) * 10000, batas_per_akun )
fee_baru    = max( 0, fee_total - fee_yang_sudah_dikreditkan )
```

Views dijumlahkan **dulu**, baru dibulatkan. Sisa di bawah 1.000 terbawa ke kelipatan berikutnya.

| Utas A | Utas B | Utas C | Total views | Fee akun | Bila dihitung per utas |
|---:|---:|---:|---:|---:|---:|
| 999 | 0 | 0 | 999 | Rp0 | Rp0 |
| 600 | 400 | 0 | 1.000 | Rp10.000 | Rp0 |
| 700 | 700 | 700 | 2.100 | Rp20.000 | Rp0 |
| 1.999 | 1.999 | 1.999 | 5.997 | Rp50.000 | Rp30.000 |

- Rate dikunci saat akun bergabung ke campaign. Semua nominal integer rupiah.
- Utas pending/rejected tidak dihitung. Utas dihapus keluar dari total untuk fee baru; kredit yang sudah masuk tidak ditagih otomatis.
- Views turun tidak mengurangi kredit. Views yang naik lagi tidak dibayar dua kali, karena yang dibayar hanya selisih di atas kredit.
- Perlu batas per akun, anggaran campaign, dan tanggal berakhir: satu utas uji 675.037 views saja bernilai Rp6.750.000. Anggaran dijaga transaksi dan row lock.
- Fee berjalan adalah **estimasi**. Masuk saldo setelah utas disetujui, masa tahan selesai, dan akun lolos pemeriksaan klik (3.6).

### 3.5 Penarikan
- Minimum Rp50.000 [usulan], ke bank atau e-wallet
- Saldo ditahan saat diajukan; admin transfer manual, lalu mengunggah bukti
- Status: `pending → paid / rejected` (rejected mengembalikan saldo)
- Semua mutasi tercatat di ledger append-only; saldo dihitung dari ledger

### 3.6 Shortlink dan filter klik per akun
Tiap akun terverifikasi mendapat satu link tetap `trycuan.online/XXXXXXX` (7 karakter acak, peka huruf besar/kecil), mengarah ke tujuan campaign aktif. Satu campaign aktif per akun [usulan].

**Wajib terpasang sebelum posting**
1. User memasang link di kolom link profil Threads, lalu menekan Cek Link.
2. Sistem mencocokkan host dan kode persis di `bio_links` milik `threads_pk`, lalu mencatat `link_verified_at`.
3. Submit ditolak bila waktu post lebih awal dari `link_verified_at`, atau link sudah tidak terpasang.
4. Dicek ulang saat refresh dan penarikan. Link hilang: akun diberi flag. Gagal membaca profil: jangan dianggap link hilang.

Tanpa cron, pelepasan link di antara dua pengecekan tidak terlihat.

**Lapisan filter klik**

| Lapis | Pemeriksaan | Bila gagal |
|---|---|---|
| 1. Request | Method, header, bot dikenal, link preview, prefetch | Tidak valid |
| 2. Token | Token bertanda tangan, sekali pakai, umur pendek, terikat kode link | Tidak valid |
| 3. Tantangan | Cloudflare Turnstile di halaman perantara, diverifikasi di server | Tidak valid |
| 4. Browser | `navigator.webdriver`, tanda headless, ketidakcocokan OS/browser/zona waktu/bahasa | Sinyal risiko; dibuang bila beberapa sinyal kuat |
| 5. Device | Cookie first-party acak + hash ciri device kasar | Satu klik valid per device per akun per campaign |
| 6. Jaringan | IP hosting/proxy/VPN/Tor, subnet, ASN, negara di luar target | Tertahan atau risiko tinggi |
| 7. Pemilik | Device/jaringan yang dipakai login freelancer | Device sama dibuang; IP sama hanya sinyal |
| 8. Pola | Ledakan klik, jarak terlalu teratur, jam tidak wajar, device seragam | Klik tertahan, akun flag |
| 9. Lintas akun | Device/cluster yang mengeklik banyak link freelancer | Semua klik cluster tertahan |

Status klik: `valid`, `held` (belum dihitung), `invalid`, beserta alasannya. Hanya `valid` yang dibandingkan dengan views.

- **IP saja tidak boleh membatalkan klik.** Banyak pengguna seluler berbagi IP operator, dan satu rumah berbagi WiFi.
- Ciri device bisa sama di HP bertipe sama, dan cookie browser bawaan aplikasi bisa terhapus. Cookie adalah penentu utama; hash device tanpa cookie hanya sinyal bila cirinya umum.
- Lapis 3 dan 6 bergantung layanan/daftar IP pihak ketiga. Bila layanan mati, klik menjadi `held`, bukan valid.
- Tombol Lanjut tetap tersedia supaya pengunjung tidak terjebak bila tantangan gagal.

**Privasi:** IP dan ciri device disimpan sebagai HMAC dengan kunci server; detail dihapus setelah 90 hari [usulan], agregat tetap. Pengumpulan dijelaskan di halaman perantara dan kebijakan privasi; kewajiban persetujuan menurut UU PDP perlu dicek sebelum publik.

**Pembanding per akun**
```
CTR akun = klik valid / total kenaikan views terverifikasi x 100%   (rentang waktu sama)
```

| Status akun | Kondisi | Pencairan |
|---|---|---|
| Data kurang | Di bawah 5.000 views [usulan] atau pembanding campaign belum cukup | Review manual |
| Wajar | CTR dalam sebaran campaign, kualitas klik baik | Lanjut ke masa tahan |
| Perlu review | CTR terlalu rendah/tinggi, banyak `held`, atau ada lonjakan | Ditahan sampai diputuskan admin |
| Ditolak | Admin menemukan bukti kecurangan | Fee belum cair dibatalkan, tercatat di audit |

Batas wajar memakai median dan sebaran campaign, bukan angka tebakan. Fee dihitung dari views; klik menjadi **gerbang pencairan**, bukan pengali atau pemotong otomatis. User melihat total views, klik valid, dan status; skor serta alasan rinci hanya untuk admin agar filter tidak mudah diakali.

## 4. Anti-kecurangan

- Masa tahan 3 hari [usulan] sejak views yang akan dikreditkan pertama terbaca; dinilai saat user berikutnya masuk
- Flag lonjakan views, rasio like/views tidak wajar, rekening sama, dan device login sama di beberapa user
- Utas dihapus/tidak terbaca membuat fee yang belum cair direview
- Semua keputusan admin tercatat; user dapat mengajukan banding

**Batas kemampuan sistem:**
- Filter tidak dapat menjamin nol klik belian. Orang sungguhan yang dibayar untuk klik dari HP masing-masing tampak seperti pengunjung biasa; device farm dan proxy seluler juga bisa lolos.
- Utas asli bisa viral dengan klik bio rendah, jadi klik rendah saja bukan bukti kecurangan.
- Klik bio bisa berasal dari utas lain yang tidak didaftarkan, sehingga CTR akun bisa lebih tinggi dari sebenarnya.

Target realistis: membuat kecurangan lebih mahal daripada fee-nya, menahan yang mencurigakan, dan membatasi kerugian dengan cap akun serta anggaran.

## 5. Database

| Tabel | Kolom utama |
|---|---|
| `users` | id, name, email unique, password_hash, role, status |
| `threads_accounts` | id, user_id unique, threads_pk unique, username, verified_at |
| `threads_verifications` | user_id, username, code, expires_at (kode tampil publik di bio, jadi tidak di-hash; hanya berlaku untuk user pembuatnya) |
| `campaigns` | id, title, brief, destination_url, rules_json, rate_per_1k, cap_per_account, target_countries, budget, starts_at, ends_at, status |
| `participations` | id, account_id, campaign_id (unique gabungan), rate_snapshot, cap_snapshot, total_views, fee_total, fee_credited, valid_clicks, trust_status, joined_at |
| `submissions` | id, participation_id, post_id unique, post_code unique (binary), posted_at, views, likes, status, flags_json, last_checked_at, last_error |
| `short_links` | id, account_id unique, code unique (binary), link_verified_at, last_seen_at, lost_at (campaign aktif diambil dari partisipasi) |
| `clicks` | id, short_link_id, campaign_id, device_id, ip_hash, subnet_hash, trait_hash, country, device_type, risk_score, status, reasons_json, created_at |
| `devices` | id, cookie_hash unique, first_seen_at, last_seen_at |
| `user_devices` | user_id, device_id (unique gabungan), last_login_at |
| `click_tokens` | nonce unique, used_at (token ditandatangani; hanya yang terpakai disimpan) |
| `valid_click_keys` | short_link_id, campaign_id, device_id (unique gabungan; mencegah hitung ganda paralel) |
| `fraud_reviews` | id, participation_id, views_delta, valid_clicks, score, reasons_json, status, reviewed_by |
| `view_snapshots` | id, submission_id, views, likes, checked_at |
| `ledger` | id, user_id, type, amount, participation_id, withdrawal_id, idempotency_key unique, created_at |
| `payout_methods` | id, user_id, provider, account_name, account_no (terenkripsi) |
| `withdrawals` | id, user_id, payout_method_id, amount, status, proof_path, processed_by, processed_at |
| `rate_limits` | key, hits, window_start |
| `scrape_logs` | id, target, http_code, outcome, duration_ms, created_at |
| `audit_logs` | id, actor_id, action, target, meta_json, created_at |
| `settings` | key, value |

## 6. Halaman

- **Publik:** landing, daftar, masuk, reset password
- **Freelancer:** dashboard, campaign dan detail, submit utas, akun Threads, insight link, saldo/penarikan, profil
- **Admin:** ringkasan, campaign, review utas, review akun (views vs klik), log klik, penarikan, user, kesehatan scraper, pengaturan

### Card akun (utama)
```
+--------------------------------------------+
| @username                        [ Wajar ] |
| Campaign: Nama campaign   Link terpasang   |
|                                            |
| 12.480 total views    Estimasi Rp120.000   |
| 8 utas terverifikasi       96 klik valid   |
| [sparkline views dan klik]                 |
| [===========-------------] 480 / 1.000     |
| 520 views lagi untuk +Rp10.000             |
|                                            |
| Update 4 menit lalu     [Detail] [Refresh] |
+--------------------------------------------+
| Utas 1   Cuplikan...    5.200   Disetujui  |
| Utas 2   Cuplikan...    3.100   Disetujui  |
| [Lihat semua 8 utas]                       |
+--------------------------------------------+
```
Angka di card hanya ilustrasi.

## 7. Arah UI

| Token | Nilai |
|---|---|
| Background / surface / border | `#0E0E10` / `#17171A` / `#29292E` |
| Teks / teks sekunder | `#F2F2F3` / `#A0A0A8` |
| Aksen / hover | `#F97316` / `#FB8A3C` |
| Teks di atas aksen | `#140A02` |

- Oranye hanya untuk aksi utama, menu aktif, dan progres. Status sukses/gagal memakai warna sendiri plus label teks.
- Compact: teks dasar 14px, jarak kelipatan 4px, radius 8px, angka `tabular-nums`
- Mobile: bottom navigation, card satu kolom, target sentuh minimal 44px. Desktop: sidebar 220px, grid 2 sampai 3 kolom.
- Skeleton saat refresh, empty state berisi langkah berikutnya, fokus keyboard terlihat

## 8. Keamanan

PDO prepared statement, `password_hash`, CSRF di semua POST, escape output, regenerasi session saat login, cookie HttpOnly/SameSite, rate limit login/verifikasi/submit/refresh, pengecekan kepemilikan di tiap endpoint, upload bukti divalidasi dan disimpan di luar `public/`, rahasia di `.env`. Scraper hanya boleh ke host Threads, termasuk setelah redirect. Tujuan shortlink hanya dari admin (mencegah open redirect). Token beacon ditandatangani dan dibatasi frekuensinya. Kode yang sama dengan route aplikasi tidak boleh dibuat.

## 9. Struktur folder

```
trycuan/
  public/           index.php, .htaccess, assets/
  app/
    Core/           Router, Database, Auth, Csrf, View
    Controllers/    publik, freelancer, Admin/
    Models/
    Services/       Threads/ (sumber, parser, guard), AccountVerifier,
                    SubmissionVerifier, AccountFeeCalculator, Ledger,
                    ShortLink, ClickTracker, ClickFilter/ (per lapis),
                    AccountTrustEvaluator
    Views/
  config/  database/migrations/  storage/  tests/fixtures/
```

## 10. Tahapan

| # | Isi | Selesai bila |
|---|---|---|
| 1 (selesai) | Fondasi: routing, DB, auth, layout UI | Daftar dan masuk jalan di mobile dan desktop |
| 2 (selesai) | Scraper + tes fixture | Sukses, post hilang, login wall, dan URL palsu terbaca benar |
| 3 (selesai) | Verifikasi akun Threads | Kode di bio cocok menautkan `pk`; kode salah/kedaluwarsa ditolak |
| 4 (selesai) | Campaign + submit utas | Utas milik orang lain, duplikat, dan lewat periode ditolak |
| 5 (selesai) | Shortlink + klik | Link di bio terdeteksi; posting sebelum link ditolak; tiap lapis filter punya tes; klik ganda paralel hanya dihitung sekali; IP bersama tidak otomatis ditolak |
| 6 | Card akun, refresh, fee | Keempat contoh fee akun lulus; utas pending tidak dihitung; views naik-turun tidak dibayar ganda; cooldown dan cap berlaku |
| 7 | Ledger + penarikan | Klik ganda/request paralel tidak menggandakan saldo |
| 8 | Panel admin | Review, bandingkan views vs klik, bayar, flag, dan log scraper berfungsi |
| 9 | Hardening + deploy | Reset password via email, Audit akses, uji dari IP VPS, backup DB |

Tahap 1 sampai 6 cukup untuk demo. Klik dari publik baru bisa diuji setelah `trycuan.online` online, karena `trycuan.test` hanya dapat dibuka di device ini. Pembayaran sungguhan baru dibuka setelah tahap 9.

## 11. Keputusan yang perlu dikonfirmasi

1. **Pemilik campaign:** hanya admin (asumsi planning), atau klien/brand juga bisa membuat?
2. **Batas fee:** berapa maksimum per akun dan anggaran per campaign?
3. **Pencairan:** transfer manual admin (asumsi), atau payment gateway otomatis?
4. **Batas akun:** satu user satu akun Threads (asumsi), atau boleh beberapa? Fee tetap dihitung terpisah per akun.
5. **Review:** semua utas disetujui admin dulu (asumsi), atau otomatis jika syarat mesin terpenuhi?
6. **Tujuan shortlink:** ke website/produk campaign (asumsi), atau halaman TryCuan?
7. **Bentuk link:** satu link tetap per akun dengan satu campaign aktif (asumsi), atau link berbeda per campaign?
