# Presensi Sekolah — Presensi Kartu RFID

Aplikasi web presensi siswa memakai kartu RFID, dengan dashboard **Laravel 13**
(berjalan di **PHP 8.3**). Layar presensi dan dashboard dirancang untuk siswa
dan guru dengan berbagai kebutuhan khusus.

## Cara kerja

```
 Siswa menempelkan kartu ──▶ Pembaca RFID USB ──▶ PC guru (browser, dashboard terbuka) ──▶ Server Laravel
                              (bekerja seperti keyboard)          │
                                                                  ▼
                       Jendela hasil besar + nada + suara: "Selamat pagi, Budi. Presensi masuk berhasil."
```

1. Pembaca kartu RFID USB dicolok ke **PC guru**.
2. Guru login dan membiarkan aplikasi terbuka (halaman apa pun, biasanya dashboard).
3. Siswa datang dan menempelkan kartu. Hasilnya langsung muncul di layar guru,
   dibunyikan, dan tercatat di server. Tidak perlu layar atau komputer tambahan.

Beberapa PC guru (mis. tiap ruang kelas) bisa dipakai bersamaan; semuanya mencatat
ke server yang sama dan log menunjukkan di PC siapa kartu ditempel.

## Fitur

**Saat kartu ditempel di PC guru**
- Kartu ringkas di tengah layar: ikon bulat berwarna status, **nama**, kelas & NIS,
  status dengan jam (Masuk / Terlambat / Pulang), dan sapaan. Bila siswa punya **foto**,
  foto tampil dengan ikon status di sudutnya. Kartu tertutup sendiri (ada garis
  penanda waktu) atau dengan tombol Tutup / Esc. Kartu yang belum terdaftar
  menampilkan nomor kartunya agar mudah didaftarkan.
- Umpan balik **ganda**: nada bunyi + **suara ucapan bahasa Indonesia** + tampilan.
- **Mode per siswa**:
  - *Utamakan suara* (mis. tunanetra): kalimat lebih lengkap & lambat, menyebut jam
    dan status ("pukul 7 lewat 5 menit… hari ini kamu terlambat").
  - *Utamakan tampilan* (mis. tunarungu): tanpa ucapan, jendela tampil lebih lama,
    bingkai tebal & cincin berdenyut pelan.
  - *Suara dan tampilan* (bawaan).
- Penanda **"Siap menerima kartu"** di bilah atas. Saat guru sedang mengetik di kotak
  isian, pembaca otomatis **dijeda** agar ketikan guru tidak terganggu.
- Di halaman *Tambah/Ubah Siswa*, kartu yang ditempel **mengisi nomor kartu**
  (bukan dicatat sebagai presensi) — mendaftarkan kartu jadi sangat mudah.
- Dashboard dan daftar presensi harian **langsung diperbarui** setelah tap.
- Status tidak hanya dibedakan warna; denyutan ≤ 1×/detik (aman untuk epilepsi
  fotosensitif); menghormati pengaturan *reduce motion*.
- Sesi login dijaga tetap aktif selama halaman terbuka, jadi pembaca tidak tiba-tiba
  berhenti di tengah pagi.

**Dashboard guru & admin**
- Ringkasan hari ini: hadir, terlambat, izin/sakit, **daftar siswa yang belum presensi**,
  tap kartu terbaru, tren 7 hari.
- Presensi harian per kelas; guru mencatat **izin / sakit / alpa** beserta catatan.
- Tombol "tandai yang belum presensi sebagai Alpa" (juga otomatis tiap sore).
- **Rekap bulanan** per kelas (H/T/I/S/A, % kehadiran) → cetak atau **unduh CSV untuk Excel**.
- Data siswa lengkap dengan jenis kekhususan, kontak wali, catatan pendamping, foto.
- **Impor siswa dari Excel (CSV)**.
- Kelas dengan jadwal sendiri (mis. SDLB pulang lebih awal), hari libur, hari sekolah.
- Peran pengguna: **Admin** dan **Guru**.
- Tampilan bisa **diperbesar** (A− A A+) dan ada **mode kontras tinggi**; huruf
  **Plus Jakarta Sans** tersimpan offline; navigasi penuh dengan keyboard; ramah
  pembaca layar.
- Warna & huruf diatur di satu tempat: bagian `:root` di `public/css/app.css`.

**Opsional:** pembaca kartu mandiri **ESP32 + RC522** lewat Wi-Fi (mis. untuk pintu
asrama) — contoh program & skema di [`hardware/`](hardware/README.md).

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
git clone <alamat-repo> presensi-sekolah
```

```bash
cd presensi-sekolah
```

```bash
composer install
```

```bash
cp .env.example .env
```

Buka `.env`, lalu sesuaikan:

```dotenv
APP_NAME="Sekolah Contoh"
APP_URL=http://192.168.1.10        # alamat server di jaringan sekolah
APP_TIMEZONE=Asia/Jakarta          # WITA: Asia/Makassar, WIT: Asia/Jayapura

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presensi_sekolah
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `presensi_sekolah` (mis. lewat phpMyAdmin), lalu:

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
`guru@sekolah.test` (kata sandi `password`).

Tanpa pembaca kartu pun alur tap bisa dicoba: buka dashboard, klik area kosong,
ketik nomor kartu seorang siswa (lihat menu Siswa), lalu tekan Enter.

### Penjadwal otomatis

Penandaan **Alpa** otomatis (bawaan pukul 16.00, ubah dengan
`PRESENSI_ABSENT_MARKING_TIME`) dan pembersihan log lama membutuhkan scheduler.

Linux (crontab):

```bash
* * * * * cd /var/www/presensi-sekolah && php artisan schedule:run >> /dev/null 2>&1
```

Windows: buat tugas di **Task Scheduler** yang berjalan tiap 1 menit dengan
program `C:\laragon\bin\php\php-8.3.x\php.exe` dan argumen
`C:\laragon\www\presensi-sekolah\artisan schedule:run`.

Tanpa scheduler pun aplikasi tetap berjalan; penandaan Alpa bisa dilakukan lewat
tombol di halaman **Presensi Harian** atau perintah:

```bash
php artisan presensi:tandai-alpa --tanggal=2026-11-11
```

## Menyiapkan PC guru

1. Colok pembaca RFID USB (jenis *keyboard emulation*). Uji di Notepad: tempel kartu,
   bila muncul angka lalu pindah baris, pembaca sudah berfungsi.
2. Buka aplikasi di Chrome/Edge (`http://IP-SERVER`), login sebagai guru, centang
   *Ingat saya*.
3. Pastikan penanda di bilah atas berbunyi **Siap menerima kartu**.
4. Tempel satu kartu untuk mencoba. Tombol **Suara** di bilah atas untuk mematikan/menyalakan suara.

> **Penting:** pembaca USB "mengetik" ke jendela yang sedang aktif. Selama jam
> presensi, biarkan **jendela browser aplikasi ini di depan**. Bila guru sedang
> membuka Word/Excel, nomor kartu akan terketik di sana, bukan tercatat.

Agar suara ucapan terdengar natural, pasang suara **Bahasa Indonesia** di Windows
(*Settings → Time & Language → Speech → Add voices → Indonesian*). Chrome yang
terhubung internet juga menyediakan suara "Google Bahasa Indonesia".

Daftarkan kartu memakai jenis pembaca yang sama dengan yang dipakai untuk presensi
(pembaca berbeda bisa membaca kartu yang sama dengan format nomor berbeda).

## Penggunaan sehari-hari

1. **Admin** mengisi *Pengaturan*, *Kelas*, *Hari Libur*, lalu data *Siswa*
   (satu per satu atau impor CSV).
2. **Daftarkan kartu**: buka *Siswa → Tambah/Ubah*, lalu tempelkan kartu siswa ke
   pembaca — nomor kartu terisi sendiri. Kartu yang ditempel di dashboard tetapi belum
   terdaftar juga bisa didaftarkan dari *Log Tap Kartu → Daftarkan kartu*.
3. Isi **jenis kekhususan** siswa; mode umpan balik akan diusulkan otomatis
   (tunanetra → utamakan suara, tunarungu → utamakan tampilan).
4. **Guru** membuka dashboard di PC yang tersambung pembaca; siswa menempelkan kartu.
5. Guru mencatat izin/sakit di *Presensi Harian* dan mengunduh *Rekap Bulanan*.

## Notifikasi ke orang tua (pengembangan lanjutan)

Setiap presensi masuk/pulang memicu event `App\Events\AttendanceRecorded`. Untuk
mengirim WhatsApp (mis. lewat Fonnte/Wablas), buat listener:

```bash
php artisan make:listener SendGuardianNotification --event=AttendanceRecorded
```

Di dalam `handle()`, ambil `$event->attendance->student->guardian_phone` lalu kirim
pesan lewat HTTP client Laravel. Jadikan listener `ShouldQueue` dan jalankan
`php artisan queue:work` agar PC guru tidak menunggu pengiriman pesan.

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
| `app/Http/Controllers/TapController.php` | Tap dari pembaca USB di PC guru |
| `app/Http/Controllers/Api/DeviceTapController.php` | API pembaca Wi-Fi (opsional) |
| `public/js/card-reader.js` | Penangkap ketikan pembaca USB, jendela hasil, nada & suara |
| `public/js/app.js`, `public/css/app.css` | Dashboard |
| `hardware/` | Panduan pembaca USB & program ESP32 |

## Sebelum dipakai di sekolah

- Set `APP_ENV=production` dan `APP_DEBUG=false` di `.env`.
- Ganti kata sandi admin, buat akun untuk setiap guru.
- Jalankan `php artisan config:cache` dan `php artisan route:cache` setelah mengubah `.env`.
- Cadangkan database secara rutin (mis. ekspor phpMyAdmin mingguan).
- Server sebaiknya memakai IP tetap di jaringan sekolah agar PC guru (dan pembaca ESP32, bila ada) selalu menemukan alamatnya.
