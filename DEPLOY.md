# Deploy TryCuan

Domain aplikasi dan shortlink harus sama: `trycuan.online`.

## 1. Server
- Ubuntu LTS, PHP 8.3 FPM (`pdo_mysql curl mbstring openssl fileinfo gd phar`), MySQL 8, Nginx
- Document root **wajib** folder `public/`. Folder lain tidak boleh bisa diakses dari web.

```nginx
server {
    listen 443 ssl http2;
    server_name trycuan.online www.trycuan.online;
    root /var/www/trycuan/public;
    index index.php;
    client_max_body_size 3m;

    location / { try_files $uri /index.php?$query_string; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 60s;
    }
    location ~ \.php$ { return 404; }
    location ~ /\. { return 404; }
}
server {
    listen 80;
    server_name trycuan.online www.trycuan.online;
    return 301 https://trycuan.online$request_uri;
}
```

Di `php.ini`: `upload_max_filesize = 10M`, `post_max_size = 12M`, `expose_php = Off`.

## 2. Database
Buat user khusus, jangan memakai root:

```sql
CREATE DATABASE trycuan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trycuan'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, LOCK TABLES ON trycuan.* TO 'trycuan'@'localhost';
```

## 3. Aplikasi
1. Clone repo, salin `.env.example` ke `.env`, isi semua nilainya, lalu `chmod 600 .env` milik user web server.
2. Buat `APP_KEY` baru: `php -r "echo bin2hex(random_bytes(32));"`. **Jangan pernah diganti** setelah ada rekening tersimpan.
3. Beri user web server izin tulis hanya ke `storage/`.
4. Jalankan `php database/migrate.php`.
5. Buat akun admin: `php bin/admin.php buat EMAIL "Nama"`, lalu masuk di `/admin/masuk` dan ganti passwordnya. Email admin tidak boleh dipakai akun freelancer.
6. Jalankan `php bin/doctor.php --threads=USERNAME --mail=ALAMAT` sampai tidak ada GAGAL.

## 4. Layanan luar
- **Cloudflare Turnstile:** buat widget untuk `trycuan.online`, isi kunci situs dan rahasianya.
- **Cloudflare proxy:** isi `TRUST_CLOUDFLARE=1` hanya setelah firewall server menolak port 80/443 dari selain IP Cloudflare. Tanpa itu, siapa pun bisa memalsukan IP dan negara. Mode SSL harus Full (strict).
- **SMTP:** pasang SPF, DKIM, dan DMARC di domain pengirim.
- **Daftar IP hosting:** isi `storage/ip/hosting.txt`, satu CIDR per baris.

## 5. Uji dari VPS
IP hosting bisa diperlakukan berbeda oleh Threads. Sebelum buka publik:
1. `php bin/doctor.php --threads=USERNAME`
2. `php bin/threads.php post URL` untuk beberapa utas, termasuk utas dengan views kecil.
3. Buka **Admin > Scraper** setelah sekitar 30 request. Hentikan bila muncul `blocked` atau `login_required` pada profil publik.
4. Buka shortlink dari beberapa HP dan jaringan, lalu cocokkan di **Log klik**.

## 6. Backup
Cron harian (hanya backup; views tetap diperbarui saat user masuk):

```
15 3 * * * cd /var/www/trycuan && php bin/backup.php 14 >> storage/logs/backup.log 2>&1
```

Salin `storage/backups/` ke tempat lain, dan simpan `.env` terpisah dari backup. Uji pemulihan ke database kosong:

```
gunzip -c storage/backups/db-XXXX.sql.gz | mysql -u trycuan -p trycuan_uji
```

## 7. Sebelum pembayaran sungguhan
- [ ] `doctor.php` tanpa GAGAL
- [ ] Uji VPS di bagian 5 berhasil
- [ ] Pemulihan backup sudah dicoba
- [ ] Reset password lewat email asli sudah dicoba
- [ ] Ketentuan layanan, kewajiban UU PDP, pajak, dan ketentuan Meta sudah ditinjau
- [ ] Kredit otomatis masih nonaktif sampai data klik nyata cukup

## Frontend
Sumber ada di `resources/`, hasilnya di `public/assets/build/` dan ikut di-commit, jadi server tidak membutuhkan Node. Setelah mengubah `resources/` atau kelas Tailwind di view: `npm install`, lalu `npm run build`.
