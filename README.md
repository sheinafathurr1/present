# Presensi Guru & Staf — Presensi dari HP dengan Lokasi GPS

Aplikasi web presensi untuk **guru dan staf tendik** sekolah dasar. Setiap orang
masuk dengan **username & kata sandi**-nya sendiri, lalu presensi masuk dan pulang
dari **HP masing-masing** saat berada di area sekolah. Kepala sekolah, wakil, dan
bendahara memantau kehadiran semua orang. Dibangun dengan **Laravel 13** di **PHP 8.3**.

## Cara kerja

```
 Guru/staf tiba di sekolah ──▶ buka situs di HP ──▶ HP membaca lokasi GPS ──▶ server
                                                     │                          │
                                     "Anda di area sekolah · 35 m"    cek radius & akurasi,
                                                     │                 jam dari server
                                                     ▼                          ▼
                                          ketuk  [ Presensi Masuk ]  ──▶  Hadir / Terlambat
```

1. Admin (TU/operator) membuat akun untuk setiap guru & staf dan mengisi **titik
   lokasi sekolah** beserta radiusnya (mis. 100 m).
2. Guru/staf membuka situs di HP, login sekali (bisa diingat), lalu menekan
   **Presensi Masuk** saat tiba dan **Presensi Pulang** saat pulang. Tombol hanya aktif
   bila HP berada di dalam radius sekolah dengan sinyal GPS yang cukup akurat.
3. Tidak masuk? Ajukan **izin, sakit, cuti, atau dinas luar** dari HP. Pengajuan
   langsung tercatat tanpa menunggu persetujuan.
4. Kepala sekolah, wakil, dan bendahara melihat **kehadiran hari ini**, **rekap bulanan**,
   dan **daftar pengajuan** — hanya melihat, tidak mengubah.
5. Yang tidak presensi dan tidak mengajukan apa pun ditandai **Alpa** otomatis sore hari.

Tidak perlu memasang aplikasi atau alat apa pun: cukup browser HP (Chrome/Safari).

## Peran

| Peran | Siapa | Bisa apa |
|---|---|---|
| **Guru/Staf** | Guru dan staf tendik | Presensi dari HP, melihat riwayat sendiri, mengajukan & membatalkan izin |
| **Pimpinan** | Kepala sekolah, wakil kepala sekolah, bendahara | Semua yang di atas (bila wajib presensi), ditambah memantau semua orang, rekap bulanan, unduh Excel, melihat lampiran izin — **hanya melihat** |
| **Admin** | TU / operator sekolah | Semua yang dilihat pimpinan, ditambah kelola akun guru & staf, koreksi presensi, pengaturan sekolah, hari libur |

Setiap akun punya pilihan **wajib presensi**. Akun operator yang tidak perlu dihitung
kehadirannya bisa dimatikan pilihannya.

## Fitur

**Presensi dari HP (guru & staf)**
- Halaman presensi dirancang untuk HP: jam (mengikuti jam server), jam kerja,
  status masuk & pulang hari ini, dan panel lokasi yang menunjukkan posisi HP
  terhadap area sekolah (*di area*, *di luar area*, *GPS belum akurat*, *akses lokasi ditolak*).
- Tombol presensi hanya aktif di dalam area. Pulang sebelum jam pulang meminta
  konfirmasi dan dicatat sebagai **pulang awal**.
- **Riwayat** per bulan: jumlah hadir/terlambat/izin/sakit/cuti/dinas luar/alpa,
  total menit terlambat, berapa kali pulang awal, dan rincian per hari.
- **Izin & cuti**: pilih jenis, tanggal (satu hari atau rentang), keterangan, dan
  lampiran opsional (foto surat dokter/surat tugas; foto dari kamera diperkecil otomatis).
  Hari libur dan akhir pekan dilewati otomatis. Pengajuan yang belum lewat bisa
  dibatalkan; yang sedang berlangsung bisa **diakhiri lebih awal** (mis. sudah sembuh).
- Ganti kata sandi sendiri.

**Pemantauan (pimpinan & admin)**
- **Kehadiran hari ini**: berapa yang sudah hadir, terlambat, izin/sakit/cuti/dinas luar,
  alpa, dan belum presensi; tren 7 hari sekolah terakhir; daftar semua orang dengan jam
  masuk/pulang, jarak dari sekolah, dan alasan izin. Bisa disaring guru / staf tendik.
  Halaman memperbarui diri tiap 2 menit.
- **Rekap bulanan**: tabel orang × tanggal (kode H/T/I/S/C/D/A), jumlah per status,
  total menit terlambat, pulang awal, dan persentase kehadiran. **Unduh Excel (CSV)** dan **Cetak**.
- **Pengajuan izin**: semua pengajuan per bulan, bisa disaring per jenis/orang, dengan lampiran.
- **Detail guru/staf**: data akun, ringkasan bulan, dan rincian per hari termasuk
  jarak & akurasi GPS saat presensi.

**Pengelolaan (admin)**
- Tambah/ubah/nonaktifkan akun guru & staf (username, kata sandi, peran, jenis guru/tendik,
  jabatan, NIP, nomor HP, jam kerja khusus). Akun yang sudah punya riwayat dinonaktifkan,
  bukan dihapus, agar riwayatnya tetap ada.
- **Impor dari Excel/CSV**: buat atau perbarui banyak akun sekaligus. Data diperiksa dulu,
  username & kata sandi bisa dibuat otomatis, lalu **kartu akun** siap dicetak.
- **Koreksi presensi** per orang per tanggal (mis. HP rusak, lupa presensi pulang, dinas
  mendadak) dengan alasan yang tercatat.
- **Pengaturan**: nama sekolah, **titik lokasi** (tombol *Gunakan lokasi saya sekarang*),
  radius, batas akurasi GPS, jam kerja guru & staf tendik, toleransi terlambat, jam buka
  presensi, hari sekolah, dan cuti bersama.
- **Hari libur**: libur nasional & cuti bersama terisi otomatis, kalender setahun, dan
  libur sekolah tambahan.

## Aturan presensi

| Kejadian | Hasil |
|---|---|
| Presensi masuk sampai *jam masuk + toleransi* | **Hadir** |
| Presensi masuk setelahnya | **Terlambat**, menit terlambat dihitung **sejak jam masuk** |
| Presensi pulang sebelum jam pulang | Tetap Hadir/Terlambat, dicatat **pulang awal X menit** |
| Menekan presensi masuk/pulang lagi | Ditolak: "Anda sudah presensi … pukul …" (satu kali masuk & satu kali pulang per hari) |
| Di luar radius sekolah | Ditolak, dengan jarak HP ke sekolah |
| Akurasi GPS lebih buruk dari batas | Ditolak, diminta menunggu/pindah ke tempat terbuka |
| Hari libur / bukan hari sekolah | Tidak ada presensi |
| Mengajukan izin/sakit/cuti/dinas luar | Langsung tercatat untuk tiap hari sekolah di rentangnya; hari yang sudah presensi masuk tidak diubah |
| Sedang izin tetapi ternyata masuk | Batalkan/akhiri pengajuan di menu Izin, lalu presensi seperti biasa |
| Tidak presensi & tidak mengajukan apa pun | **Alpa** otomatis (bawaan pukul 16.00) |

- Jam presensi selalu diambil dari **server**, bukan dari HP.
- Jam kerja bawaan: guru 07.00–13.00, staf tendik 07.00–15.00, toleransi 15 menit
  (semua bisa diubah). Orang tertentu bisa diberi jam kerja khusus.
- Pengajuan izin bisa sampai 7 hari ke belakang (mis. sakit dan baru sempat lapor) dan
  90 hari ke depan. Batas ini bisa diubah di `.env`.

## Kebutuhan

- PHP **8.3.x** dengan ekstensi `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl`,
  `zip`, `xmlreader` (dua terakhir untuk impor Excel; `pdo_sqlite` hanya untuk tes otomatis)
- Composer 2
- **MySQL 8 / MariaDB 10.6+**
- **Hosting online dengan HTTPS** (sertifikat SSL, mis. Let's Encrypt yang biasanya gratis
  di hosting). Browser HP **hanya membagikan lokasi lewat alamat `https://`** — tanpa HTTPS
  tombol presensi tidak akan pernah aktif. Pengecualian: `http://localhost` saat mencoba di komputer sendiri.
- Tidak perlu Node.js — CSS & JavaScript sudah siap pakai tanpa proses build.

## Instalasi

```bash
git clone <alamat-repo> presensi-sekolah
```

```bash
cd presensi-sekolah
```

```bash
cp .env.example .env
```

Buka `.env` dan sesuaikan:

```dotenv
APP_NAME="SD Negeri Contoh 01"
APP_ENV=production                 # biarkan "local" bila hanya ingin mencoba dengan data contoh
APP_DEBUG=false
APP_URL=https://presensi.sekolah-anda.sch.id
APP_TIMEZONE=Asia/Jakarta          # WITA: Asia/Makassar, WIT: Asia/Jayapura

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presensi_sekolah
DB_USERNAME=...
DB_PASSWORD=...
```

Lalu jalankan:

```bash
composer run setup
```

Perintah tersebut memasang dependensi, membuat kunci aplikasi, **membuat database bila
belum ada**, membuat semua tabel, mengisi libur nasional, dan membuat **akun admin**
(username `admin`). Kata sandinya ditampilkan di layar — catat lalu ganti setelah login.
Anda juga bisa menentukannya lewat `ADMIN_USERNAME` dan `ADMIN_PASSWORD` di `.env`.

Bila user database tidak boleh membuat database (umum di hosting), buat dulu database
lewat panel hosting (collation `utf8mb4_unicode_ci`).

Di hosting, arahkan *document root* domain ke folder **`public`**.

### Langkah pertama setelah login sebagai admin

1. **Pengaturan → Lokasi presensi.** Buka halaman ini **dari HP** sambil berdiri di tengah
   sekolah, ketuk **Gunakan lokasi saya sekarang**, tunggu akurasinya kecil, lalu simpan.
   (Bisa juga menyalin koordinat dari Google Maps.) Atur radius sesuai luas sekolah.
2. **Pengaturan → Jam kerja & hari sekolah.**
3. **Guru & Staf → Impor Excel** untuk membuat semua akun sekaligus (lihat di bawah), atau
   **Tambah** satu per satu. Tandai kepala sekolah, wakil, dan bendahara dengan peran
   **Pimpinan**. Berikan username & kata sandi secara pribadi.
4. Minta setiap guru/staf membuka alamat aplikasi di HP, login, dan **mengizinkan akses lokasi**
   saat diminta browser. Sarankan menambahkannya ke layar utama (menu browser → *Tambahkan ke
   Layar utama*) agar mudah dibuka tiap hari.

### Impor guru & staf dari Excel

Menu **Guru & Staf → Impor Excel**:

1. Unduh **template Excel** (atau CSV). Isi satu baris per orang di lembar *Data guru & staf*;
   lembar *Petunjuk* menjelaskan setiap kolom. Hanya kolom `nama` yang wajib.
2. Unggah file. Semua baris **diperiksa dan ditampilkan dulu** (akun baru, diperbarui, atau
   yang perlu diperbaiki beserta nomor barisnya). Bila ada satu saja yang salah, tidak ada
   yang disimpan — perbaiki file lalu unggah ulang.
3. Simpan. Username yang kosong dibuat dari nama (`Drs. Ahmad Fauzi, M.Pd.` → `ahmad.fauzi`),
   kata sandi yang kosong dibuat acak (mis. `kmbt4827`). Keduanya **ditampilkan sekali**:
   cetak **kartu akun** untuk dibagikan atau unduh daftarnya (CSV).

Catatan:

- Kolom yang dikenali: `nama`, `username`, `kata_sandi`, `jenis` (guru/tendik), `jabatan`,
  `nip`, `no_hp`, `peran` (pegawai/pimpinan/admin), `wajib_presensi` (ya/tidak), `jam_masuk`,
  `jam_pulang`. Nama lain yang umum juga dikenali (mis. "Nama Lengkap", "HP", "Jenis PTK"),
  dan baris judul tidak harus di baris pertama — **file ekspor Dapodik** bisa langsung dipakai.
- Akun dicocokkan lewat username, lalu NIP, lalu nama yang sama persis. Mengimpor ulang file
  yang sama **memperbarui** akun tersebut, bukan membuat akun ganda. Sel kosong tidak menghapus data.
- **NIP 18 digit** harus berformat Teks di Excel. Template sudah menyiapkannya; bila NIP terlanjur
  berformat Angka (tampil seperti `1,98E+17`), digit terakhirnya sudah berubah dan baris itu ditolak.
- Format `.xls` lama: buka di Excel lalu simpan sebagai *Excel Workbook (.xlsx)*.

### Data uji coba

Selama `APP_ENV=local`, `composer run setup` dan `php artisan db:seed` juga mengisi
**data uji coba lengkap**: 1 admin, kepala sekolah, wakil, bendahara, 9 guru, 3 staf tendik,
dan 1 akun nonaktif; titik sekolah contoh; riwayat presensi sejak awal bulan lalu (hadir,
terlambat, pulang awal, lupa pulang, alpa); pengajuan sakit/izin/cuti/dinas luar; dan
presensi hari ini sesuai jam saat seeder dijalankan.

Semua akun memakai kata sandi `password`:

| Username | Peran |
|---|---|
| `admin` | Admin (operator) |
| `kepsek`, `wakasek`, `bendahara` | Pimpinan |
| `guru` | Guru |
| `tendik` | Staf tendik |

Untuk mencoba presensi dari laptop, buka `http://localhost:8000`, lalu di Chrome DevTools pilih
**More tools → Sensors → Location** dan isi koordinat dekat titik sekolah contoh
(`-6.2000`, `106.8167`).

Mengulang dari awal (menghapus semua data):

```bash
php artisan migrate:fresh --seed
```

> **Penting:** versi ini mengganti struktur database versi kartu RFID sebelumnya. Bila Anda
> sudah pernah memasang versi lama, jalankan `php artisan migrate:fresh --seed` (semua data
> lama terhapus).

### Hari libur nasional

Libur nasional dan cuti bersama Indonesia diisi otomatis dari kalender publik
"Hari libur di Indonesia" (format `.ics`):

- saat `composer run setup` / `php artisan db:seed`,
- otomatis tiap **Senin pukul 05.30** (butuh scheduler, lihat di bawah),
- lewat tombol **Perbarui libur nasional** di menu Hari Libur, atau `php artisan presensi:sinkron-libur`.

Tanpa internet, libur bertanggal tetap (Tahun Baru, Hari Buruh, Pancasila, 17 Agustus,
Natal) dan libur yang mengikuti Paskah tetap terisi. Libur nasional bisa ditandai
**tetap masuk sekolah** bila sekolah tidak libur.

### Penjadwal otomatis

Penandaan Alpa harian dan pembaruan libur nasional membutuhkan scheduler.
Di hosting (menu *Cron Jobs*) atau server Linux, tambahkan:

```
* * * * * cd /path/ke/presensi-sekolah && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa scheduler aplikasi tetap berjalan; penandaan Alpa bisa dijalankan manual:

```bash
php artisan presensi:tandai-alpa --tanggal=2026-11-18
```

## Tentang lokasi & privasi

- Lokasi HP **hanya dibaca saat halaman presensi terbuka** dan **hanya dikirim saat tombol
  presensi ditekan** — tidak ada pelacakan di luar itu.
- Yang disimpan: koordinat, perkiraan akurasi, dan jarak ke sekolah pada saat presensi
  masuk dan pulang. Pimpinan dan admin melihat **jarak & akurasinya** (bukan peta).
- Lampiran izin disimpan di folder privat server (`storage/app/private`) dan hanya bisa
  dibuka oleh pemiliknya, pimpinan, dan admin.
- Informasikan hal ini kepada guru & staf sebelum aplikasi dipakai.

**Keterbatasan GPS.** Lokasi dari browser bisa dipalsukan dengan aplikasi "fake GPS" di
Android. Aplikasi membatasi akurasi dan mencatat jarak setiap presensi sehingga pola yang
janggal bisa diperiksa pimpinan. Bila diperlukan pengamanan lebih ketat, pertimbangkan
tambahan seperti pembatasan jaringan Wi-Fi sekolah atau swafoto. Di dalam gedung beratap
beton, akurasi GPS bisa menurun: atur batas akurasi dan radius sesuai kondisi sekolah.

## Penggunaan sehari-hari

- **Guru/staf:** buka aplikasi di HP → tunggu tulisan *Anda di area sekolah* → **Presensi Masuk**.
  Saat pulang → **Presensi Pulang**. Tidak masuk → menu **Izin** → **Ajukan**.
- **Pimpinan:** buka **Hari ini** untuk melihat siapa yang belum hadir; **Rekap** di akhir bulan
  (unduh Excel atau cetak).
- **Admin:** perbaiki presensi yang bermasalah lewat **Guru & Staf → nama → Koreksi**.

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
| `app/Services/AttendanceService.php` | Semua aturan presensi: masuk/pulang dengan GPS, izin, koreksi admin, penandaan alpa |
| `app/Support/SchoolSettings.php` | Pengaturan sekolah (tabel `settings`): lokasi, radius, jam kerja, hari sekolah |
| `app/Support/Geo.php` | Perhitungan jarak (haversine) |
| `app/Support/DailySummary.php`, `MonthlyReport.php` | Ringkasan hari ini & rekap bulanan |
| `app/Services/NationalHolidaySync.php` | Pengambilan libur nasional |
| `app/Services/EmployeeImporter.php` | Membaca, memeriksa, dan menyimpan impor guru & staf (Excel/CSV) |
| `public/js/presence.js` | Pembacaan lokasi GPS & tombol presensi di HP |
| `public/js/app.js`, `public/css/app.css` | Skrip & tampilan umum |

## Sebelum dipakai di sekolah

- Pastikan situs berjalan di **HTTPS**. Tambahkan `SESSION_SECURE_COOKIE=true` di `.env`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Atur titik lokasi sekolah dan coba presensi dari beberapa titik (gerbang, ruang guru,
  ruang kelas terjauh) untuk memastikan radius & batas akurasi pas.
- Ganti kata sandi admin.
- Jalankan `php artisan config:cache` dan `php artisan route:cache` setelah mengubah `.env`.
- Pasang cron scheduler.
- Cadangkan database secara rutin (mis. ekspor mingguan dari panel hosting).
