# Presensi SLB — Presensi Kartu RFID untuk Sekolah Luar Biasa

Aplikasi web presensi siswa memakai kartu RFID, dengan dashboard **Laravel 13**
(berjalan di **PHP 8.3**). Layar presensi dan dashboard dirancang untuk siswa
dan guru dengan berbagai kebutuhan khusus.

## Fitur

**Layar presensi (kiosk)**
- Siswa cukup menempelkan kartu; tidak perlu mengetik atau mengklik apa pun.
- Umpan balik **ganda**: nada bunyi + suara ucapan bahasa Indonesia + layar warna
  penuh dengan ikon, kata, nama besar, dan foto siswa.
- **Mode per siswa**:
  - *Utamakan suara* (mis. tunanetra): kalimat lebih lengkap & lambat, menyebut jam
    dan status ("pukul 7 lewat 5 menit… hari ini kamu terlambat").
  - *Utamakan tampilan* (mis. tunarungu): tanpa ucapan, layar tampil lebih lama,
    bingkai tebal & cincin berdenyut pelan.
  - *Suara dan tampilan* (bawaan).
- Disapa dengan **nama panggilan** dan foto agar mudah dikenali (tunagrahita, autis).
- Status tidak hanya dibedakan warna (aman untuk buta warna); denyutan ≤ 1×/detik
  (aman untuk epilepsi fotosensitif); menghormati pengaturan *reduce motion*.
- Huruf **Atkinson Hyperlegible** (dirancang Braille Institute untuk pembaca
  low vision), disertakan offline.
- Jam mengikuti server, sesi tetap aktif, dan otomatis muat ulang tiap malam.

**Dashboard guru & admin**
- Ringkasan hari ini yang memperbarui diri: hadir, terlambat, izin/sakit, belum presensi,
  tap kartu terbaru, tren 7 hari, status perangkat.
- Presensi harian per kelas; guru mencatat **izin / sakit / alpa** beserta catatan.
- Tombol "tandai yang belum presensi sebagai Alpa" (juga otomatis tiap sore).
- **Rekap bulanan** per kelas (H/T/I/S/A, % kehadiran) → cetak atau **unduh CSV untuk Excel**.
- Data siswa lengkap dengan jenis kekhususan, kontak wali, catatan pendamping, foto.
- **Daftarkan kartu** cukup dengan menempelkannya di pembaca USB; kartu tak dikenal
  yang ditempel di pembaca mana pun bisa langsung dipilih.
- **Impor siswa dari Excel (CSV)**.
- Kelas dengan jadwal sendiri (mis. SDLB pulang lebih awal), hari libur, hari sekolah.
- Peran pengguna: **Admin**, **Guru**, **Akun Kiosk** (hanya layar presensi).
- Tampilan bisa **diperbesar** (A− A A+) dan ada **mode kontras tinggi**; navigasi
  penuh dengan keyboard, label & pesan galat ramah pembaca layar.

**Pembaca kartu**
- **Pembaca USB** (mode keyboard) + browser di komputer mana pun — paling mudah.
- **Pembaca jaringan ESP32 + RC522** lewat API ber-token — contoh program & skema
  rangkaian di [`hardware/`](hardware/README.md).

## Aturan presensi

1. Tap **pertama** hari itu = **Masuk**. Lewat dari *batas jam masuk* → **Terlambat**
   (hanya pada hari sekolah).
2. Tap berikutnya **sebelum** *jam pulang* → hanya diberi tahu "sudah presensi".
3. Tap berikutnya **mulai** *jam pulang* = **Pulang**.
4. Tap berulang dalam *jeda tap ganda* (bawaan 60 detik) diabaikan.
5. Siswa yang belum presensi sampai sore ditandai **Alpa** (dilewati pada hari libur).
6. Bila guru sudah mencatat izin/sakit tetapi siswa ternyata datang dan menempelkan
   kartu, statusnya berubah menjadi hadir/terlambat (catatan tetap tersimpan).

Jam masuk, jam pulang, jeda, dan hari sekolah diatur di menu **Pengaturan**; tiap
kelas boleh punya jadwal sendiri.

## Kebutuhan

- PHP **8.3.x** dengan ekstensi `pdo_mysql` (atau `pdo_sqlite`), `mbstring`, `intl`,
  `fileinfo`, `gd`, `openssl`, `zip`
- Composer 2
- MySQL 8 / MariaDB 10.6+ (disarankan) atau SQLite
- Tidak perlu Node.js — CSS & JavaScript sudah siap pakai tanpa proses build.

Cocok dijalankan di **Laragon** atau **XAMPP** (Windows) maupun server Linux di
jaringan lokal sekolah. Aplikasi tidak membutuhkan internet setelah terpasang.

## Instalasi

```bash
git clone <alamat-repo> presensi-slb
```

```bash
cd presensi-slb
```

```bash
composer install
```

```bash
cp .env.example .env
```

Buka `.env`, lalu sesuaikan:

```dotenv
APP_NAME="SLB Negeri Contoh"
APP_URL=http://192.168.1.10        # alamat server di jaringan sekolah
APP_TIMEZONE=Asia/Jakarta          # WITA: Asia/Makassar, WIT: Asia/Jayapura

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presensi_slb
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `presensi_slb` (mis. lewat phpMyAdmin), lalu:

```bash
php artisan key:generate
```

```bash
php artisan migrate --seed
```

```bash
php artisan storage:link
```

Perintah `migrate --seed` membuat **akun admin** dan menampilkan kata sandinya di
layar — catat lalu ganti setelah login. Anda juga bisa menentukannya sendiri lewat
`ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env` sebelum menjalankan seed.

Uji coba cepat:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka `http://localhost:8000` (atau `http://IP-SERVER:8000` dari komputer lain).

### Data contoh (opsional, jangan di server produksi)

```bash
php artisan db:seed --class=DemoSeeder
```

Membuat 4 kelas, 25 siswa beserta kartu, riwayat 3 minggu, serta akun
`guru@sekolah.test` dan `kiosk@sekolah.test` (kata sandi `password`).

### Penjadwal otomatis

Penandaan **Alpa** otomatis (bawaan pukul 16.00, ubah dengan
`PRESENSI_ABSENT_MARKING_TIME`) dan pembersihan log lama membutuhkan scheduler.

Linux (crontab):

```bash
* * * * * cd /var/www/presensi-slb && php artisan schedule:run >> /dev/null 2>&1
```

Windows: buat tugas di **Task Scheduler** yang berjalan tiap 1 menit dengan
program `C:\laragon\bin\php\php-8.3.x\php.exe` dan argumen
`C:\laragon\www\presensi-slb\artisan schedule:run`.

Tanpa scheduler pun aplikasi tetap berjalan; penandaan Alpa bisa dilakukan lewat
tombol di halaman **Presensi Harian** atau perintah:

```bash
php artisan presensi:tandai-alpa --tanggal=2026-11-11
```

## Penggunaan sehari-hari

1. **Admin** mengisi *Pengaturan*, *Kelas*, *Hari Libur*, lalu data *Siswa*
   (satu per satu atau impor CSV).
2. **Daftarkan kartu**: buka *Siswa → Ubah*, klik kotak *Nomor kartu*, tempelkan kartu
   ke pembaca USB. Atau tempel kartu baru di pembaca mana pun, lalu buka
   *Log Tap Kartu → Daftarkan kartu*.
3. Isi **jenis kekhususan** siswa; mode umpan balik kiosk akan diusulkan otomatis
   (tunanetra → utamakan suara, tunarungu → utamakan tampilan).
4. Pasang **layar kiosk** di gerbang (lihat [`hardware/README.md`](hardware/README.md)).
5. **Guru** memantau dashboard, mencatat izin/sakit di *Presensi Harian*, dan
   mengunduh *Rekap Bulanan*.

## Notifikasi ke orang tua (pengembangan lanjutan)

Setiap presensi masuk/pulang memicu event `App\Events\AttendanceRecorded`. Untuk
mengirim WhatsApp (mis. lewat Fonnte/Wablas), buat listener:

```bash
php artisan make:listener SendGuardianNotification --event=AttendanceRecorded
```

Di dalam `handle()`, ambil `$event->attendance->student->guardian_phone` lalu kirim
pesan lewat HTTP client Laravel. Jadikan listener `ShouldQueue` dan jalankan
`php artisan queue:work` agar layar kiosk tidak menunggu pengiriman pesan.

## Untuk pengembang

```bash
php artisan test
```

```bash
./vendor/bin/pint
```

Struktur penting:

| Lokasi | Isi |
|---|---|
| `app/Services/AttendanceService.php` | Semua aturan tap kartu, izin manual, penandaan alpa |
| `app/Services/TapOutcome.php` | Teks layar, ucapan, dan baris LCD untuk setiap hasil tap |
| `app/Support/SchoolSettings.php` | Pengaturan sekolah (tabel `settings`) |
| `app/Support/MonthlyReport.php` | Matriks rekap bulanan |
| `app/Http/Controllers/KioskController.php` | Layar kiosk (pembaca USB) |
| `app/Http/Controllers/Api/DeviceTapController.php` | API pembaca jaringan |
| `public/js/kiosk.js`, `public/css/kiosk.css` | Layar kiosk |
| `public/js/app.js`, `public/css/app.css` | Dashboard |
| `hardware/` | Panduan & program ESP32 |

## Sebelum dipakai di sekolah

- Set `APP_ENV=production` dan `APP_DEBUG=false` di `.env`.
- Ganti kata sandi admin, buat akun guru & kiosk masing-masing.
- Jalankan `php artisan config:cache` dan `php artisan route:cache` setelah mengubah `.env`.
- Cadangkan database secara rutin (mis. ekspor phpMyAdmin mingguan).
- Server sebaiknya memakai IP tetap di jaringan sekolah agar pembaca ESP32 tidak kehilangan alamat.
