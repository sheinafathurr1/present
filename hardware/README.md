# Perangkat keras pembaca kartu

Cara utama: **pembaca RFID USB dicolok ke PC guru**. Pembaca kartu Wi-Fi
(ESP32) hanya opsional, misalnya untuk pintu asrama yang tidak ada PC-nya.

> **Penting:** daftarkan kartu siswa memakai jenis pembaca yang sama dengan yang
> dipakai untuk presensi. Pembaca USB sering mengirim nomor kartu dalam format
> desimal (mis. `0004283792`), sedangkan ESP32 + RC522 mengirim format
> heksadesimal (mis. `A1B2C3D4`). Kartu yang sama bisa terbaca berbeda.

## A. Pembaca USB di PC guru (utama)

| Komponen | Keterangan |
|---|---|
| Pembaca RFID USB "keyboard emulation" | 125 kHz (EM4100) atau 13,56 MHz (Mifare), harga ±Rp100–250 rb, tanpa driver |
| Kartu / gantungan kunci RFID | Sesuaikan frekuensinya dengan pembaca |
| PC/laptop guru | Chrome atau Edge versi terbaru |
| Speaker | Wajib bila ada siswa tunanetra |

Langkah:

1. Colok pembaca ke PC guru. Buka Notepad, tempel kartu: bila muncul angka lalu
   pindah baris, pembaca sudah berfungsi.
2. Buka aplikasi di browser, login sebagai guru (centang *Ingat saya*).
3. Penanda di bilah atas harus berbunyi **Siap menerima kartu**. Siswa bisa langsung
   menempelkan kartu; hasilnya tampil besar di layar guru dan diucapkan.
4. Biarkan **jendela browser tetap di depan** selama jam presensi. Pembaca USB
   mengetik ke jendela yang aktif — bila guru sedang di Word/Excel, nomor kartu
   akan terketik di sana.

Tips penempatan untuk siswa berkebutuhan khusus:

- Letakkan pembaca di tepi meja yang mudah dijangkau pengguna kursi roda, dan beri
  penanda timbul/tekstur di permukaannya agar mudah ditemukan siswa tunanetra.
- Arahkan monitor sedikit ke arah siswa agar jendela hasil (nama, foto, warna)
  bisa dilihat siswa tunarungu.
- Kartu bisa dipasang di gantungan leher/gelang bagi siswa yang kesulitan memegang kartu.

Suara ucapan memakai mesin suara bawaan sistem. Agar pelafalan bahasa Indonesia
bagus, pasang suara **Bahasa Indonesia** (Windows: *Settings → Time & Language →
Speech → Add voices → Indonesian*). Chrome yang terhubung internet juga
menyediakan suara "Google Bahasa Indonesia".

## B. Pembaca Wi-Fi ESP32 + RC522 (opsional)

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

1. Tambahkan pembaca di menu **Pembaca Wi-Fi** pada dashboard, salin tokennya.
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
