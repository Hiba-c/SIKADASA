# SIKADASA

Sistem Informasi Keuangan dan Administrasi Dasawisma Cempaka 46.

Panduan ini mencakup persiapan, instalasi, konfigurasi, penggunaan lokal, backup, dan pemeliharaan SIKADASA. Untuk Windows, langkah-langkah berikut menggunakan PowerShell dan Laragon.

## 1. Persyaratan

Siapkan:

- Windows 10/11 atau Windows Server yang didukung.
- Laragon dengan PHP 8.2 atau lebih baru dan MySQL 8/MariaDB.
- Ekstensi PHP `intl`, `pdo_mysql`, dan `zip` aktif. Ekstensi `intl` digunakan untuk menghitung kalender Umm al-Qura; `zip` untuk arsip backup.
- Composer 2.
- Node.js versi LTS dan npm.
- Ruang penyimpanan untuk database, dependensi, file publik, dan backup.

Periksa program yang tersedia di PowerShell:

```powershell
php -v
php -m
composer --version
node --version
npm --version
```

Pastikan daftar `php -m` memuat `intl`, `pdo_mysql`, dan `zip`. Jika belum, aktifkan ekstensi tersebut melalui konfigurasi PHP Laragon yang sedang digunakan, lalu mulai ulang Laragon dan buka terminal baru. Pastikan PHP yang dipakai oleh terminal, Composer, dan server Laragon mengarah ke versi yang sama.

## 2. Siapkan proyek dan database

1. Letakkan atau clone proyek ke folder kerja. Contoh lokasi Laragon:

   ```text
   C:\laragon\www\SIKADASA
   ```

2. Jalankan Laragon, lalu mulai layanan web server dan MySQL.
3. Buat database baru bernama `db_sikadasa` dengan charset `utf8mb4` dan collation `utf8mb4_unicode_ci` melalui phpMyAdmin atau alat database lain.
4. Buka PowerShell pada folder proyek:

   ```powershell
   Set-Location C:\laragon\www\SIKADASA
   ```

Jangan gunakan database yang sudah berisi data aplikasi lain. Migrasi akan menambah struktur tabel SIKADASA ke database yang dikonfigurasi.

## 3. Pasang dependensi

Jalankan dari folder proyek:

```powershell
composer install
npm install
```

Jika `composer install` menyatakan ekstensi PHP tidak tersedia, aktifkan ekstensi yang disebut pada PHP Laragon yang digunakan terminal, lalu ulangi perintah.

## 4. Konfigurasi aplikasi

1. Salin `.env.example` menjadi `.env`:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Buka `.env` dengan editor teks dan isi nilai lokal yang sesuai. Variabel yang perlu diperiksa:

   | Variabel | Pengaturan |
   |---|---|
   | `APP_ENV`, `APP_DEBUG`, `APP_URL` | Gunakan nilai pengembangan untuk komputer lokal; untuk server produksi ikuti bagian pemasangan produksi. |
   | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE` | Sesuaikan dengan driver dan database yang dibuat pada langkah sebelumnya. |
   | `DB_USERNAME`, `DB_PASSWORD` | Isi kredensial MySQL lokal; jangan gunakan kredensial contoh atau kredensial produksi bersama. |

3. Buat kunci enkripsi aplikasi:

   ```powershell
   php artisan key:generate
   ```

4. Hapus cache konfigurasi lama jika proyek sebelumnya pernah dijalankan:

   ```powershell
   php artisan optimize:clear
   ```

## 5. Buat tabel dan data awal

Jalankan migrasi dan seeder:

```powershell
php artisan migrate --seed
```

## 6. Jalankan aplikasi untuk pengembangan

Build aset frontend:

```powershell
npm run build
```

Jalankan server Laravel:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Biarkan proses Vite tetap berjalan saat mengembangkan UI. Untuk menjalankan pemeriksaan:

```powershell
npm run typecheck
npm run build
php artisan test
```

Test menggunakan SQLite sementara sesuai `phpunit.xml`; test tidak ditujukan untuk mengubah data MySQL lokal.

## 7. Konfigurasi aplikasi

### Akun dan peran

- **Ketua:** persetujuan pinjaman, perubahan status anggota, pengelolaan akun pengguna, audit log, backup/pemulihan, dan persetujuan batch pencairan tabungan.
- **Bendahara:** pencatatan transaksi harian dan pemrosesan batch pencairan yang telah disetujui.
- Akun Ketua dan Bendahara tambahan dapat dikelola Ketua dari menu **Pengguna**.
- Ganti password awal setelah login. Perubahan password dilakukan dari **Profil**; password saat mengedit pengguna bersifat opsional.

### Pengaturan keuangan

Nilai awal aplikasi:

- Jasa pinjaman: **0,5% dari pokok pinjaman**.
- Iuran arisan: nilai awal diatur melalui `config/sikadasa.php`.
- Kalender pencairan tabungan: perkiraan Umm al-Qura untuk H-10 Idulfitri. Ketua mengonfirmasi tanggal sebelum membuat batch; tanggal resmi Indonesia dapat berbeda.
- Jika kas tidak cukup untuk pencairan tabungan, pembayaran dibagi proporsional dan sisa batch dapat diproses kemudian saat kas tersedia.
- Pinjaman yang belum lunas ditampilkan sebagai peringatan, tetapi tidak memblokir pencairan tabungan.

Nilai aktif tertentu disimpan di database pada pengaturan organisasi. Perubahan nilai fallback pada `config/sikadasa.php` tidak otomatis menimpa pengaturan yang sudah ada di database.

### Bahasa, zona waktu, dan email

- Bahasa aplikasi menggunakan Bahasa Indonesia.
- Zona waktu aplikasi menggunakan `Asia/Jakarta`.
- Mailer `.env.example` menggunakan `log`; email tidak dikirim ke alamat tujuan. Log email lokal, jika ada, dicatat oleh Laravel.
- Fitur pengiriman email/pemulihan password belum dikonfigurasi sebagai layanan email produksi.

## 8. Backup harian pada Windows

Backup otomatis dibuat setiap hari pukul **02.00 waktu aplikasi**, menyimpan database MySQL dan file publik terpilih. Arsip berada di:

```text
storage\app\private\backups
```

Retensi lokal adalah 30 hari. Backup membutuhkan `mysqldump.exe`, `mysql.exe`, dan ekstensi PHP `zip`.

### Uji backup manual

Jalankan:

```powershell
php artisan sikadasa:backup
```

Periksa pesan sukses dan file ZIP terbaru di `storage\app\private\backups`. Jika Laragon tidak menambahkan lokasi MySQL ke `PATH`, cari programnya:

```powershell
Get-Command mysqldump.exe
Get-Command mysql.exe
```

### Aktifkan jadwal dengan Task Scheduler

Jadwal Laravel baru berjalan otomatis jika Windows memanggil scheduler. Buat task di **Task Scheduler**:

1. Pilih **Create Task** dan beri nama, misalnya `SIKADASA Laravel Scheduler`.
2. Pada **Triggers**, buat pemicu harian dan aktifkan pengulangan setiap **1 menit** dengan durasi **Indefinitely**.
3. Pada **Actions**, pilih **Start a program**:
   - **Program/script:** lokasi `php.exe` Laragon, misalnya `laragon\bin\php\php-8.2.x\php.exe`.
   - **Add arguments:** `artisan schedule:run`
   - **Start in:** `SIKADASA`
4. Sesuaikan opsi Conditions/Settings dengan kebijakan komputer. Pastikan task tetap berjalan saat komputer dinyalakan dan akun Windows yang menjalankannya dapat membaca proyek serta menulis ke folder `storage`.
5. Simpan task. Masuk ke Task Scheduler Library, jalankan task secara manual sekali, lalu pastikan statusnya tidak gagal.
6. Untuk memeriksa scheduler secara manual:

   ```powershell
   Set-Location C:\laragon\www\SIKADASA
   php artisan schedule:list
   php artisan schedule:run
   ```

Perintah `schedule:run` hanya menjalankan pekerjaan yang waktunya telah tiba. Lakukan uji pencadangan langsung dengan `php artisan sikadasa:backup`; jangan menunggu pukul 02.00 untuk memeriksa koneksi database atau `mysqldump`.

### Pemulihan backup

Pemulihan akan menimpa data database saat ini. Sebelum memulai:

1. Pastikan arsip yang dipilih adalah backup yang benar dan dapat dipercaya.
2. Cadangkan data saat ini, atau pastikan backup pengaman dapat dibuat.
3. Masuk sebagai Ketua, buka menu **Backup**, pilih arsip, dan ketik `PULIHKAN` untuk mengonfirmasi.
4. Tunggu proses selesai. Pemulihan mengakhiri sesi login; masuk kembali setelahnya.
5. Periksa anggota, saldo, transaksi, dan halaman laporan setelah pemulihan.

Jangan menguji restore dengan menimpa database produksi atau database yang berisi data penting. Simpan salinan backup juga di media/server lain; backup yang hanya berada di disk yang sama tidak melindungi dari kehilangan disk atau server.

## 9. Pemasangan produksi

Untuk pemasangan yang diakses melalui internet:

1. Gunakan server terkelola dengan PHP 8.2+, ekstensi yang diperlukan, MySQL/MariaDB, Composer, dan Node.js/npm untuk proses build.
2. Atur `.env` produksi ke mode production, nonaktifkan debug, setel URL HTTPS domain sebenarnya, serta gunakan kredensial database dengan hak akses minimum.
3. Buat `APP_KEY` baru dan password akun awal yang kuat dan berbeda. Jangan salin `.env` dari mesin lain tanpa meninjau rahasianya.
4. Arahkan document root web server ke folder `public` proyek, bukan root proyek. Aktifkan HTTPS.
5. Berikan izin tulis kepada akun web server hanya pada folder Laravel yang memerlukannya, terutama `storage` dan `bootstrap\cache`. Jangan membuat `.env`, `vendor`, atau file backup dapat diunduh publik.
6. Pasang dependensi dan aset:

   ```powershell
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   php artisan migrate --force
   php artisan db:seed --force
   php artisan optimize
   ```

   Jalankan migrasi dan seeder hanya setelah memastikan `.env` mengarah ke database produksi yang benar. Seeder awal tidak mengubah akun yang username-nya sudah ada.
7. Aktifkan Task Scheduler untuk akun layanan/server yang dapat menjalankan PHP dan menulis ke folder penyimpanan. Konfigurasikan backup MySQL dan uji restore pada lingkungan terpisah.
8. Setelah deployment, uji login kedua peran, pencatatan transaksi, laporan PDF, audit, backup, dan akses HTTPS.

Jangan menjalankan `php artisan serve` sebagai web server produksi. Pastikan mode debug dinonaktifkan di server yang berisi data nyata.

## 10. Pemeriksaan dan pemecahan masalah

| Gejala | Pemeriksaan |
|---|---|
| `could not find driver` atau koneksi MySQL gagal | Pastikan `pdo_mysql` aktif, MySQL menyala, dan host/port/database/user/password pada `.env` benar. |
| `No application encryption key has been specified` | Jalankan `php artisan key:generate`, lalu `php artisan optimize:clear`. |
| Seeder menolak password akun | Isi dua password `SIKADASA_*_PASSWORD` yang berbeda dan masing-masing minimal 12 karakter. |
| `intl` tidak tersedia atau kalender tidak dapat dihitung | Aktifkan ekstensi PHP `intl` pada versi PHP yang digunakan web server dan terminal, lalu mulai ulang layanan. |
| Backup tidak menemukan `mysqldump` atau `mysql` | Periksa `PATH` atau isi `SIKADASA_MYSQLDUMP_BINARY` dan `SIKADASA_MYSQL_BINARY` dengan path yang benar. |
| Scheduler tidak membuat backup | Pastikan Task Scheduler memanggil `php artisan schedule:run` setiap menit, memakai PHP yang benar, folder kerja proyek, dan akun Windows yang memiliki izin tulis. |
| Halaman menampilkan aset lama atau tampilan rusak | Jalankan `npm run build`, lalu muat ulang browser. Untuk pengembangan gunakan `npm run dev`. |
| Error aplikasi | Periksa `storage\logs\laravel.log`; jangan menyalin isi log yang memuat data pribadi atau rahasia ke tempat publik. |

Perintah bantuan dan pemeriksaan:

```powershell
php artisan about
php artisan route:list
php artisan schedule:list
php artisan migrate:status
php artisan test
```

Jangan memasukkan password, token, `.env`, data anggota, atau isi database ke laporan masalah publik.
