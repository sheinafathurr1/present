# Perangkat keras pembaca kartu

Aplikasi mendukung **dua jenis pembaca**. Boleh dipakai bersamaan, misalnya
pembaca USB di gerbang utama dan pembaca ESP32 di pintu asrama.

> **Penting:** daftarkan kartu siswa memakai jenis pembaca yang sama dengan yang
> dipakai untuk presensi. Pembaca USB sering mengirim nomor kartu dalam format
> desimal (mis. `0004283792`), sedangkan ESP32 + RC522 mengirim format
> heksadesimal (mis. `A1B2C3D4`). Kartu yang sama bisa terbaca berbeda.

## A. Pembaca USB + layar kiosk (paling mudah)

| Komponen | Keterangan |
|---|---|
| Pembaca RFID USB "keyboard emulation" | 125 kHz (EM4100) atau 13,56 MHz (Mifare), harga ±Rp100–250 rb |
| Kartu RFID | Sesuaikan frekuensinya dengan pembaca |
| Komputer / mini PC / laptop bekas | Cukup bisa menjalankan Chrome / Edge |
| Monitor | Disarankan ≥ 21 inci, dipasang setinggi mata siswa (juga untuk pengguna kursi roda) |
| Speaker | Wajib untuk siswa tunanetra |

Langkah:

1. Colok pembaca ke komputer. Buka Notepad, tempel kartu: bila muncul angka lalu
   pindah baris, pembaca sudah berfungsi.
2. Di dashboard tambahkan perangkat jenis **Pembaca USB + layar kiosk**.
3. Buat akun berperan **Akun Kiosk** di menu Pengguna.
4. Di komputer kiosk, login dengan akun kiosk (centang *Ingat saya*), pilih perangkat.
5. Tekan **Mulai presensi** (atau langsung tempel kartu pertama) agar suara aktif.

Tips menjalankan Chrome dalam mode kiosk saat komputer menyala (Windows):

```bat
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --autoplay-policy=no-user-gesture-required http://SERVER/kiosk/1
```

`--autoplay-policy=no-user-gesture-required` membuat suara langsung aktif tanpa
perlu diklik. Letakkan pintasan tersebut di folder *Startup* Windows.

Suara ucapan memakai mesin suara bawaan sistem. Agar pelafalan bahasa Indonesia
bagus, pasang suara **Bahasa Indonesia** (Windows: *Settings → Time & Language →
Speech → Add voices → Indonesian*). Chrome yang terhubung internet juga
menyediakan suara "Google Bahasa Indonesia".

## B. Pembaca jaringan ESP32 + RC522

Contoh program ada di [`esp32-rc522/esp32-rc522.ino`](esp32-rc522/esp32-rc522.ino).

| Komponen | Jumlah |
|---|---|
| ESP32 DevKit V1 | 1 |
| Modul RC522 13,56 MHz + kartu/gantungan Mifare | 1 |
| LCD 16x2 + modul I2C (opsional) | 1 |
| Buzzer aktif 5 V | 1 |
| LED hijau & merah + resistor 220 Ω | 2 |
| Adaptor 5 V 2 A + kotak casing | 1 |

### Rangkaian

| RC522 | ESP32 |
|---|---|
| SDA (SS) | GPIO 5 |
| SCK | GPIO 18 |
| MOSI | GPIO 23 |
| MISO | GPIO 19 |
| RST | GPIO 4 |
| 3.3V | 3V3 (**jangan** ke 5 V) |
| GND | GND |

| Lainnya | ESP32 |
|---|---|
| LCD I2C SDA / SCL | GPIO 21 / GPIO 22 |
| Buzzer (+) | GPIO 25 |
| LED hijau | GPIO 26 |
| LED merah | GPIO 27 |

### Pemasangan

1. Tambahkan perangkat jenis **Pembaca jaringan** di dashboard, salin tokennya.
2. Isi `WIFI_SSID`, `WIFI_PASSWORD`, `SERVER_URL`, dan `DEVICE_TOKEN` di program.
3. Unggah ke ESP32 lewat Arduino IDE.
4. Server harus bisa diakses dari jaringan Wi-Fi yang sama (jalankan server dengan
   IP LAN, mis. `php artisan serve --host=0.0.0.0` untuk uji coba, atau Apache/Nginx).

### Pola bunyi

| Bunyi | Arti |
|---|---|
| 2× pendek + LED hijau | Masuk tepat waktu |
| 3× pendek + LED hijau | Masuk, terlambat |
| 1× panjang + LED hijau | Pulang |
| 1× pendek | Sudah presensi sebelumnya |
| Panjang + LED merah | Kartu belum terdaftar / galat |

Pola bunyi yang berbeda membantu siswa tunanetra memahami hasil tanpa layar.

### Format API

```http
POST /api/v1/taps
Authorization: Bearer dev_xxxxxxxx
Content-Type: application/json

{"uid": "A1B2C3D4"}
```

Jawaban (selalu HTTP 200 bila token benar):

```json
{
  "ok": true,
  "result": "check_in",
  "tone": "success",
  "headline": "Masuk",
  "message": "Selamat pagi, Budi! Presensi masuk pukul 06.52.",
  "display": ["Budi", "MASUK 06:52"],
  "student": {"name": "Budi Santoso", "class": "Kelas 1 SDLB-A", "...": "..."}
}
```

Nilai `result`: `check_in`, `late`, `check_out`, `duplicate`, `already_in`,
`already_out`, `unknown_card`, `inactive`. Nilai `tone`: `success`, `warning`,
`checkout`, `info`, `error`. Token salah → HTTP 401, perangkat nonaktif → HTTP 403.

`GET /api/v1/ping` dapat dipakai untuk mengecek koneksi dan jam server.

> Program contoh ini belum diuji pada perangkat fisik oleh pembuatnya; periksa
> kembali pin dan alamat LCD (0x27/0x3F) sesuai modul yang Anda pakai.
