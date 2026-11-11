/*
 * Pembaca kartu presensi jaringan: ESP32 + RC522 (13,56 MHz / Mifare)
 * ------------------------------------------------------------------
 * Mengirim UID kartu ke server Laravel lewat Wi-Fi, lalu memberi umpan balik
 * dengan buzzer, LED, dan (opsional) LCD 16x2 I2C.
 *
 * Library (Arduino IDE → Tools → Manage Libraries):
 *   - MFRC522            (GithubCommunity)
 *   - ArduinoJson        (Benoit Blanchon) versi 7.x
 *   - LiquidCrystal I2C  (Frank de Brabander)   ← hanya bila USE_LCD = 1
 * Board: "ESP32 Dev Module" (paket esp32 by Espressif).
 *
 * Opsional: cara utama presensi adalah pembaca USB di PC guru. Program ini
 * untuk titik tanpa PC, misalnya pintu asrama.
 *
 * Rangkaian (lihat juga hardware/README.md):
 *   RC522  SDA → GPIO 5   SCK → GPIO 18   MOSI → GPIO 23   MISO → GPIO 19
 *          RST → GPIO 4   3.3V → 3V3      GND → GND        (IRQ tidak dipakai)
 *   LCD    SDA → GPIO 21  SCL → GPIO 22   VCC → 5V (VIN)   GND → GND
 *   Buzzer aktif  (+) → GPIO 25, (−) → GND
 *   LED hijau     → GPIO 26 (lewat resistor 220 Ω)
 *   LED merah     → GPIO 27 (lewat resistor 220 Ω)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>

// ======================= UBAH BAGIAN INI =======================
const char* WIFI_SSID     = "NamaWiFiSekolah";
const char* WIFI_PASSWORD = "kata-sandi-wifi";

// Alamat server di jaringan sekolah + /api/v1/taps
const char* SERVER_URL    = "http://192.168.1.10/api/v1/taps";

// Token dari menu "Pembaca Wi-Fi" di dashboard (ditampilkan sekali).
const char* DEVICE_TOKEN  = "dev_GANTI_DENGAN_TOKEN_PERANGKAT";

#define USE_LCD 1   // ganti 0 bila tidak memakai LCD
// ===============================================================

#define PIN_RC522_SS   5
#define PIN_RC522_RST  4
#define PIN_BUZZER     25
#define PIN_LED_GREEN  26
#define PIN_LED_RED    27

#if USE_LCD
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
LiquidCrystal_I2C lcd(0x27, 16, 2);   // alamat umum 0x27 atau 0x3F
#endif

MFRC522 rfid(PIN_RC522_SS, PIN_RC522_RST);

String lastUid = "";
unsigned long lastReadAt = 0;

void showLines(const String& line1, const String& line2) {
  Serial.println("[LCD] " + line1 + " | " + line2);
#if USE_LCD
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1.substring(0, 16));
  lcd.setCursor(0, 1);
  lcd.print(line2.substring(0, 16));
#endif
}

void showIdle() {
  showLines("Tempelkan kartu", WiFi.status() == WL_CONNECTED ? "Siap" : "Wi-Fi terputus");
}

void beep(int times, int durationMs) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(durationMs);
    digitalWrite(PIN_BUZZER, LOW);
    delay(90);
  }
}

// Pola bunyi dibuat berbeda agar bisa dibedakan tanpa melihat layar.
void feedback(const String& tone) {
  digitalWrite(PIN_LED_GREEN, LOW);
  digitalWrite(PIN_LED_RED, LOW);

  if (tone == "success") {            // masuk tepat waktu: 2 bunyi pendek
    digitalWrite(PIN_LED_GREEN, HIGH);
    beep(2, 80);
  } else if (tone == "warning") {     // masuk terlambat: 3 bunyi pendek
    digitalWrite(PIN_LED_GREEN, HIGH);
    beep(3, 80);
  } else if (tone == "checkout") {    // pulang: 1 bunyi panjang
    digitalWrite(PIN_LED_GREEN, HIGH);
    beep(1, 300);
  } else if (tone == "info") {        // sudah presensi: 1 bunyi pendek
    digitalWrite(PIN_LED_GREEN, HIGH);
    beep(1, 80);
  } else {                            // kartu tidak dikenal / galat: bunyi panjang + LED merah
    digitalWrite(PIN_LED_RED, HIGH);
    beep(1, 700);
  }

  delay(1500);
  digitalWrite(PIN_LED_GREEN, LOW);
  digitalWrite(PIN_LED_RED, LOW);
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  showLines("Menghubungkan", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 15000) {
    delay(300);
  }

  if (WiFi.status() == WL_CONNECTED) {
    showLines("Wi-Fi OK", WiFi.localIP().toString());
    delay(800);
  } else {
    showLines("Wi-Fi gagal", "Coba lagi...");
    delay(1500);
  }
}

String readUid() {
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

void sendTap(const String& uid) {
  if (WiFi.status() != WL_CONNECTED) {
    showLines("Wi-Fi terputus", "Hubungi guru");
    feedback("error");
    return;
  }

  showLines("Memproses...", uid);

  HTTPClient http;
  http.begin(SERVER_URL);
  http.setTimeout(6000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + DEVICE_TOKEN);

  int status = http.POST("{\"uid\":\"" + uid + "\"}");

  if (status <= 0) {
    Serial.println("HTTP error: " + http.errorToString(status));
    http.end();
    showLines("Server tdk ada", "Hubungi guru");
    feedback("error");
    return;
  }

  String body = http.getString();
  http.end();
  Serial.println("HTTP " + String(status) + ": " + body);

  JsonDocument doc;
  if (deserializeJson(doc, body)) {
    showLines("Respon salah", "HTTP " + String(status));
    feedback("error");
    return;
  }

  String line1 = doc["display"][0] | "";
  String line2 = doc["display"][1] | "";
  String tone = doc["tone"] | "error";

  showLines(line1, line2);
  feedback(tone);
}

void setup() {
  Serial.begin(115200);

  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_LED_GREEN, OUTPUT);
  pinMode(PIN_LED_RED, OUTPUT);

#if USE_LCD
  lcd.init();
  lcd.backlight();
#endif

  SPI.begin();
  rfid.PCD_Init();

  connectWiFi();
  beep(1, 60);
  showIdle();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    showIdle();
  }

  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    delay(40);
    return;
  }

  String uid = readUid();
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  // Kartu yang masih menempel jangan dikirim berulang-ulang.
  if (uid == lastUid && millis() - lastReadAt < 4000) return;
  lastUid = uid;
  lastReadAt = millis();

  sendTap(uid);
  showIdle();
}
