# Webcam Guard — Panduan Pengguna Lengkap

Panduan lengkap untuk **Admin/Trainer** dan **Peserta**.

Versi 0.8.0

---

## Daftar Isi

- [Bagian 1: Untuk Admin/Trainer](#bagian-1-untuk-admintrainer)
  - [1.1 Apa itu Webcam Guard?](#11-apa-itu-webcam-guard)
  - [1.2 Dashboard Webcam Guard](#12-dashboard-webcam-guard)
  - [1.3 Mengaktifkan Webcam Guard di Quiz](#13-mengaktifkan-webcam-guard-di-quiz)
  - [1.4 Penjelasan Lengkap Setiap Setting](#14-penjelasan-lengkap-setiap-setting)
  - [1.5 Melihat Report](#15-melihat-report)
  - [1.6 Memahami Risk Score](#16-memahami-risk-score)
  - [1.7 Review Attempt](#17-review-attempt)
  - [1.8 Live Monitoring](#18-live-monitoring)
  - [1.9 Kirim Info ke Peserta](#19-kirim-info-ke-peserta)
  - [1.10 Konfigurasi LiveKit (Global)](#110-konfigurasi-livekit-global)
  - [1.11 Konfigurasi Data Retention (Global)](#111-konfigurasi-data-retention-global)
  - [1.12 Troubleshooting Admin/Trainer](#112-troubleshooting-admintrainer)
- [Bagian 2: Untuk Peserta](#bagian-2-untuk-peserta)
  - [2.1 Apa itu Webcam Guard?](#21-apa-itu-webcam-guard)
  - [2.2 Persiapan Sebelum Quiz](#22-persiapan-sebelum-quiz)
  - [2.3 Langkah-Langkah Memulai Quiz](#23-langkah-langkah-memulai-quiz)
  - [2.4 Verifikasi Identitas](#24-verifikasi-identitas)
  - [2.5 Selama Mengerjakan Quiz](#25-selama-mengerjakan-quiz)
  - [2.6 Menerima Info dari Trainer](#26-menerima-info-dari-trainer)
  - [2.7 Menyelesaikan Quiz](#27-menyelesaikan-quiz)
  - [2.8 Yang Boleh dan Tidak Boleh](#28-yang-boleh-dan-tidak-boleh)
  - [2.9 Troubleshooting Peserta](#29-troubleshooting-peserta)
- [FAQ](#faq)

---

# Bagian 1: Untuk Admin/Trainer

## 1.1 Apa itu Webcam Guard?

Webcam Guard adalah plugin Moodle yang menambahkan **pengawasan webcam otomatis** ke Quiz. Plugin ini memungkinkan Anda:

- **Memantau peserta** selama mengerjakan quiz melalui webcam
- **Mendeteksi pelanggaran** secara otomatis (wajah tidak terlihat, banyak wajah, pindah tab, kamera mati)
- **Mengambil foto (snapshot)** sebagai bukti pelanggaran
- **Melihat risk score** per attempt untuk mengetahui tingkat risiko kecurangan
- **Review attempt** dengan timeline event dan foto
- **Live monitoring** — melihat webcam peserta secara langsung (opsional, perlu LiveKit)
- **Mengirim info** langsung ke layar peserta selama quiz berlangsung

### Arsitektur Singkat

```
┌─────────────────────────────────────────────────┐
│  Quiz Settings (per quiz)                       │
│  ─ Enable/Disable Webcam Guard                  │
│  ─ Atur threshold, interval, identitas          │
└──────────────────┬──────────────────────────────┘
                   │
       ┌───────────▼───────────┐
       │  Preflight Check      │
       │  (saat buka quiz)     │
       │  ─ Consent checkbox   │
       │  ─ Webcam preview     │
       │  ─ Identity verify    │
       └───────────┬───────────┘
                   │
       ┌───────────▼───────────┐
       │  Monitor (saat quiz)  │
       │  ─ Face detection 1s  │
       │  ─ Blur detection     │
       │  ─ Snapshot capture   │
       └───────────┬───────────┘
                   │
       ┌───────────▼───────────┐
       │  Report & Review      │
       │  ─ Event timeline     │
       │  ─ Risk score         │
       │  ─ Teacher review     │
       └───────────────────────┘
```

---

## 1.2 Dashboard Webcam Guard

### Apa itu Dashboard?

Dashboard adalah halaman ringkasan yang menampilkan **semua quiz** di mata kuliah Anda yang menggunakan Webcam Guard.

### Cara Mengakses

1. Buka **mata kuliah** yang memiliki quiz dengan Webcam Guard
2. Di menu navigasi course, klik **"Webcam Guard Dashboard"**
3. Atau: buka salah satu quiz → klik **"View Webcam Guard report"**

### Apa yang Ditampilkan?

| Kolom | Keterangan |
|-------|-----------|
| **Nama Quiz** | Nama quiz dengan Webcam Guard aktif |
| **Total Events** | Jumlah event monitoring yang tercatat |
| **Total Violations** | Jumlah pelanggaran yang terdeteksi |
| **Attempts with Violations** | Jumlah attempt yang punya pelanggaran |
| **Actions** | Link ke report detail per quiz |

---

## 1.3 Mengaktifkan Webcam Guard di Quiz

### Langkah-Langkah

1. **Login** ke Moodle sebagai admin/trainer
2. Buka **mata kuliah** yang ingin diatur
3. Klik **nama quiz** yang ingin diawasi
4. Klik **"Settings"** di menu quiz
5. Scroll ke bawah ke bagian **"Webcam Guard"**
6. **Centang "Enable Webcam Guard"**
7. Atur setting sesuai kebutuhan (lihat bagian 1.4)
8. Klik **"Save and return to course"** atau **"Save changes"**

![Quiz Settings](pix/guide/09.png)

---

## 1.4 Penjelasan Lengkap Setiap Setting

Berikut penjelasan detail setiap setting di bagian **Webcam Guard** pada quiz settings:

---

### ✅ Enable Webcam Guard

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif (tidak dicentang) |
| **Fungsi** | Mengaktifkan atau menonaktifkan seluruh fitur Webcam Guard untuk quiz ini |

**Penjelasan:**
- Jika **tidak dicentang**: Webcam Guard tidak berlaku untuk quiz ini. Peserta bisa mengerjakan quiz tanpa webcam.
- Jika **dicentang**: Semua fitur Webcam Guard aktif. Peserta harus mengizinkan webcam sebelum mulai quiz, dan sistem akan memantau selama quiz berlangsung.

**Kapan digunakan:** Centang untuk semua quiz yang ingin diawasi. Jangan centang untuk quiz latihan atau quiz yang tidak memerlukan pengawasan.

---

### 📸 Capture Snapshot on Violation

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Aktif (dicentang) |
| **Fungsi** | Mengambil foto dari webcam peserta saat terdeteksi pelanggaran |

**Penjelasan:**
- Jika **dicentang**: Setiap kali sistem mendeteksi pelanggaran (wajah tidak terlihat, banyak wajah, pindah tab, kamera mati), sistem akan otomatis mengambil foto dari webcam peserta dan menyimpannya sebagai bukti.
- Jika **tidak dicentang**: Pelanggaran tetap dicatat sebagai event, tapi tanpa foto. Anda hanya tahu ada pelanggaran, tapi tidak bisa melihat bukti visual.

**Kapan digunakan:** Disarankan **selalu dicentang** agar Anda punya bukti visual saat review. Foto sangat membantu untuk menentukan apakah pelanggaran disengaja atau tidak.

**Catatan teknis:** Foto dikompres ke JPEG dengan kualitas 72%, lebar maksimal 640px. Ukuran rata-rata 50-100KB per foto. Disimpan di Moodle File API.

---

### ⏱️ Interval Snapshots

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Off (tidak aktif) |
| **Pilihan** | Off, Every 60 seconds, Every 120 seconds, Every 300 seconds |
| **Fungsi** | Mengambil foto secara berkala selama quiz berlangsung, terlepas dari ada pelanggaran atau tidak |

**Penjelasan:**
- **Off**: Foto hanya diambil saat ada pelanggaran (jika "Capture snapshot on violation" aktif).
- **Every 60 seconds**: Sistem mengambil foto dari webcam peserta setiap 60 detik. Ini memberikan "timeline visual" lengkap tentang apa yang dilakukan peserta selama quiz.
- **Every 120 seconds**: Foto setiap 2 menit. Kompromi antara detail dan penyimpanan.
- **Every 300 seconds**: Foto setiap 5 meni. Cukup untuk monitoring ringan.

**Kapan digunakan:**
- **Off**: Untuk quiz biasa, cukup andalkan snapshot saat pelanggaran.
- **60 detik**: Untuk ujian penting (UAS, ujian akhir) di mana Anda ingin bukti lengkap.
- **120-300 detik**: Untuk monitoring ringan tanpa terlalu banyak foto.

**Dampak penyimpanan:**
| Interval | Foto per jam per peserta | Ukuran per jam |
|----------|------------------------|----------------|
| Off | 0 (hanya saat pelanggaran) | ~0 KB |
| 60s | ~60 foto | ~3-6 MB |
| 120s | ~30 foto | ~1.5-3 MB |
| 300s | ~12 foto | ~0.6-1.2 MB |

---

### 👤 No-face Threshold (detik)

| | |
|---|---|
| **Tipe** | Angka (text input) |
| **Default** | 10 detik |
| **Range** | 1-300 detik |
| **Fungsi** | Berapa lama wajah peserta boleh tidak terlihat di kamera sebelum dianggap pelanggaran |

**Penjelasan:**
Sistem mendeteksi wajah di kamera setiap 1 detik. Jika wajah tidak terdeteksi secara terus-menerus selama waktu yang ditentukan, sistem akan mencatat event `no_face` sebagai pelanggaran dan mengambil foto (jika snapshot aktif).

**Contoh:**
- Threshold **10 detik**: Peserta melihat ke bawah selama 11 detik → pelanggaran tercatat
- Threshold **30 detik**: Peserta melihat ke bawah selama 25 detik → tidak dianggap pelanggaran
- Threshold **3 detik**: Sangat ketat — peserta kedip terlalu lama pun bisa tercatat

**Rekomendasi:**
| Jenis Quiz | Threshold | Alasan |
|------------|-----------|--------|
| Quiz latihan | 30 detik | Tidak perlu ketat |
| Ujian biasa | 10 detik (default) | Cukup untuk kebanyakan kasus |
| Ujian penting | 5-7 detik | Lebih ketat |
| Ujian sangat ketat | 3 detik | Sangat ketat, banyak false positive |

**Tips:** Jangan terlalu rendah (< 5 detik) karena peserta normal sesekali melihat ke bawah untuk membaca soal. Ini bisa menghasilkan banyak pelanggaran yang sebenarnya tidak disengaja.

---

### 👥 Multiple-face Threshold (detik)

| | |
|---|---|
| **Tipe** | Angka (text input) |
| **Default** | 3 detik |
| **Range** | 1-300 detik |
| **Fungsi** | Berapa lama lebih dari satu wajah boleh terlihat di kamera sebelum dianggap pelanggaran |

**Penjelasan:**
Sistem mendeteksi jumlah wajah di kamera setiap 1 detik. Jika terdeteksi lebih dari satu wajah secara terus-menerus selama waktu yang ditentukan, sistem akan mencatat event `multiple_faces` sebagai pelanggaran.

**Contoh:**
- Threshold **3 detik**: Ada orang lewat di belakang peserta selama 4 detik → pelanggaran tercatat
- Threshold **10 detik**: Orang duduk di sebelah peserta selama 8 detik → tidak dianggap pelanggaran

**Rekomendasi:**
| Jenis Quiz | Threshold | Alasan |
|------------|-----------|--------|
| Quiz biasa | 3 detik (default) | Cukup untuk kebanyakan kasus |
| Ujian penting | 2 detik | Lebih ketat |
| Lingkungan ramai | 5-10 detik | Hindari false positive dari orang lewat |

**Tips:** Jika peserta mengerjakan di tempat umum (perpustakaan, kafe), threshold rendah bisa menghasilkan banyak false positive. Pertimbangkan untuk menaikkan threshold.

---

### 🖥️ Tab/Window Blur Threshold (detik)

| | |
|---|---|
| **Tipe** | Angka (text input) |
| **Default** | 5 detik |
| **Range** | 1-300 detik |
| **Fungsi** | Berapa lama peserta boleh pindah tab atau window sebelum dianggap pelanggaran |

**Penjelasan:**
Sistem mendeteksi ketika peserta **pindah ke tab lain** atau **minimize browser**. Jika durasi pindah tab melebihi threshold, sistem mencatat event `window_blur` sebagai pelanggaran.

**Apa yang terdeteksi:**
- ✅ Pindah ke tab lain di browser
- ✅ Minimize browser
- ✅ Buka aplikasi lain (focus berpindah dari browser)
- ✅ Klik di luar browser
- ❌ **Tidak terdeteksi:** Membuka tab lain di device berbeda (HP, tablet lain)

**Contoh:**
- Threshold **5 detik**: Peserta pindah tab selama 6 detik → pelanggaran tercatat
- Threshold **15 detik**: Peserta pindah tab selama 10 detik → tidak dianggap pelanggaran

**Rekomendasi:**
| Jenis Quiz | Threshold | Alasan |
|------------|-----------|--------|
| Quiz biasa | 5 detik (default) | Memberi toleransi untuk notifikasi |
| Ujian penting | 3 detik | Lebih ketat |
| Open-book quiz | Off (angka sangat tinggi) | Peserta boleh buka materi |

**Tips:** Threshold 5 detik memberi toleransi untuk notifikasi popup (WhatsApp, email) yang muncul di atas browser. Jika terlalu rendah (1-2 detik), peserta bisa tercatat pelanggaran hanya karena notifikasi muncul.

---

### 📹 Enable Optional Live Monitoring

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif |
| **Fungsi** | Mengizinkan trainer meminta streaming webcam live dari peserta selama quiz berlangsung |

**Penjelasan:**
- Jika **dicentang**: Trainer bisa membuka dashboard live monitoring dan melihat webcam peserta secara langsung (real-time) selama mereka mengerjakan quiz. Ini menggunakan teknologi LiveKit (WebRTC).
- Jika **tidak dicentang**: Fitur live monitoring tidak tersedia. Trainer hanya bisa melihat snapshot dan event log.

**Prasyarat:** LiveKit harus dikonfigurasi di Site administration (lihat bagian 1.10). Jika LiveKit belum dikonfigurasi, checkbox ini tidak akan berpengaruh.

**Kapan digunakan:**
- **Aktifkan** untuk ujian sangat penting di mana Anda ingin pengawasan real-time
- **Nonaktifkan** untuk quiz biasa (cukup dengan snapshot dan event log)

**Catatan:** Live monitoring membutuhkan bandwidth server yang lebih besar karena streaming video WebRTC. Pastikan server Anda cukup kuat.

---

### 🔍 Verify Identity with Profile Picture

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif |
| **Fungsi** | Memverifikasi identitas peserta dengan membandingkan wajah di webcam dengan foto profil Moodle |

**Penjelasan:**
- Jika **dicentang**: Saat peserta membuka quiz, sistem akan mengambil foto dari webcam dan membandingkannya dengan foto profil Moodle peserta menggunakan teknologi face recognition (face-api.js). Sistem menghitung persentase kecocokan dan menampilkannya di lingkaran similarity.
- Jika **tidak dicentang**: Tidak ada verifikasi identitas. Peserta hanya perlu menunjukkan wajah di kamera.

**Prasyarat:** Peserta harus sudah mengupload foto profil Moodle yang menunjukkan wajah dengan jelas. Jika foto profil belum diupload, sistem akan menampilkan peringatan.

**Kapan digunakan:**
- **Aktifkan** untuk ujian penting di mana identitas peserta harus diverifikasi
- **Nonaktifkan** untuk quiz biasa atau jika peserta belum upload foto profil

**Catatan teknis:** Verifikasi menggunakan face-api.js dengan model TinyFaceDetector + FaceRecognition. Dilakukan di browser peserta (client-side), bukan di server. Wajah tidak dikirim ke server selama verifikasi.

---

### 📊 Identity Match Threshold

| | |
|---|---|
| **Tipe** | Angka (text input) |
| **Default** | 60 |
| **Range** | 30-90 |
| **Fungsi** | Menentukan seberapa ketat verifikasi identitas. Semakin kecil, semakin ketat. |

**Penjelasan:**
Threshold ini menentukan **jarak Euclidean** maksimum antara face descriptor webcam dan face descriptor foto profil. Face descriptor adalah vektor 128 dimensi yang merepresentasikan fitur wajah.

- **Nilai kecil (30-40)**: Sangat ketat — hanya wajah yang sangat mirip yang dianggap cocok. Cocok untuk identik kembar atau foto profil sangat bagus.
- **Nilai sedang (50-60)**: Standar — cocok untuk kebanyakan kasus. Wajah yang cukup mirip akan dianggap cocok.
- **Nilai besar (70-90)**: Longgar — wajah yang agak mirip pun dianggap cocok. Bisa kurang aman.

**Rekomendasi:**
| Threshold | Tingkat | Kapan digunakan |
|-----------|---------|-----------------|
| 30-40 | Sangat ketat | Ujian sangat penting, foto profil sangat bagus |
| 50-60 | Sedang (default) | Kebanyakan ujian |
| 70-90 | Longgar | Jika banyak peserta punya foto profil kurang bagus |

**Tips:** Jika banyak peserta gagal verifikasi padahal mereka orang yang sama, coba naikkan threshold. Jika banyak peserta yang bukan orang yang sama lolos verifikasi, turunkan threshold.

---

### 🚫 If Identity Does Not Match

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Flag |
| **Pilihan** | Flag, Block |
| **Fungsi** | Menentukan apa yang terjadi ketika verifikasi identitas gagal |

**Penjelasan:**

**Flag (Tandai di Report):**
- Peserta **tetap bisa mulai quiz** meskipun identitas tidak cocok
- Pelanggaran identitas **dicatat di report** sebagai event `identity_check` dengan status "mismatch"
- Trainer bisa **mereview** attempt ini dan mengambil keputusan nanti
- Cocok untuk: kebanyakan kasus, menghindari masalah teknis (pencahayaan buruk, foto profil kurang bagus)

**Block (Halangi Mulai):**
- Peserta **tidak bisa mulai quiz** jika identitas tidak cocok
- Peserta harus **cocok dulu** sebelum tombol "Start attempt" aktif
- Jika peserta tidak bisa cocok setelah beberapa percobaan, mereka perlu **menghubungi trainer**
- Cocok untuk: ujian sangat penting di mana identitas harus dipastikan sebelum mulai

**Rekomendasi:**
| Mode | Kapan digunakan |
|------|-----------------|
| **Flag** | Kebanyakan kasus. Lebih aman secara teknis, trainer bisa review nanti |
| **Block** | Ujian sangat penting, akademik integritas tinggi |

**Tips:** Gunakan **Flag** terlebih dahulu. Jika banyak peserta melaporkan masalah teknis (tidak bisa mulai karena verifikasi gagal), beralih ke Flag. Block hanya untuk kasus di mana identitas harus 100% dipastikan.

---

### Ringkasan Setting Rekomendasi

**Untuk Quiz Biasa (Latihan, Kuis Harian):**
| Setting | Nilai |
|---------|-------|
| Enable Webcam Guard | ✅ |
| Snapshot on violation | ✅ |
| Interval snapshots | Off |
| No-face threshold | 10 detik |
| Multiple-face threshold | 3 detik |
| Blur threshold | 5 detik |
| Live monitoring | ❌ |
| Identity verification | ❌ |

**Untuk Ujian Penting (UAS, UTS):**
| Setting | Nilai |
|---------|-------|
| Enable Webcam Guard | ✅ |
| Snapshot on violation | ✅ |
| Interval snapshots | Every 120 seconds |
| No-face threshold | 7 detik |
| Multiple-face threshold | 3 detik |
| Blur threshold | 5 detik |
| Live monitoring | ✅ (jika ada LiveKit) |
| Identity verification | ✅ (Flag mode) |
| Identity threshold | 60 |

**Untuk Ujian Sangat Penting (Ujian Akhir, Sertifikasi):**
| Setting | Nilai |
|---------|-------|
| Enable Webcam Guard | ✅ |
| Snapshot on violation | ✅ |
| Interval snapshots | Every 60 seconds |
| No-face threshold | 5 detik |
| Multiple-face threshold | 2 detik |
| Blur threshold | 3 detik |
| Live monitoring | ✅ |
| Identity verification | ✅ (Block mode) |
| Identity threshold | 50 |

---

## 1.5 Melihat Report

### Langkah-Langkah

1. Buka **quiz** yang menggunakan Webcam Guard
2. Di halaman quiz, klik **"View Webcam Guard report"**
3. Atau: Quiz → Administration → Webcam Guard report

![Report Page](pix/guide/10.png)

### Apa yang Ditampilkan?

**Summary Cards (Kartu Ringkasan):**
- **Total Events**: Jumlah total event monitoring
- **Total Violations**: Jumlah total pelanggaran
- **Attempts with Violations**: Jumlah attempt yang punya pelanggaran
- **Top Violations**: Jenis pelanggaran yang paling sering terjadi

**Tabel Review:**
- **Student**: Nama peserta
- **Attempt**: Nomor attempt
- **Status**: Pending/Cleared/Suspicious
- **Events**: Jumlah event monitoring
- **Violation**: Jumlah pelanggaran
- **Risk Score**: Skor risiko
- **Top Violation Type**: Jenis pelanggaran terbanyak
- **Actions**: Link ke detail attempt

**Filter:**
- **All**: Semua attempt
- **Pending**: Belum direview
- **Cleared**: Sudah dinilai aman
- **Suspicious**: Dicurigai kecurangan

---

## 1.6 Memahami Risk Score

Risk score adalah **skor numerik** yang menunjukkan tingkat risiko kecurangan.

### Bobot per Jenis Pelanggaran

| Jenis Pelanggaran | Bobot | Penjelasan |
|-------------------|-------|-----------|
| **No face** | 2 poin | Wajah tidak terlihat di kamera |
| **Multiple faces** | 4 poin | Lebih dari satu wajah terdeteksi |
| **Window blur** | 3 poin | Peserta pindah tab/window |
| **Camera stopped** | 5 poin | Kamera berhenti/mati |
| **Camera error** | 3 poin | Error teknis kamera |
| **Identity check** | 4 poin | Identitas tidak cocok foto profil |

### Level Risiko

| Score | Level | Warna | Interpretasi |
|-------|-------|-------|-------------|
| 0 | **Aman** | 🟢 Hijau | Tidak ada pelanggaran |
| 1-4 | **Rendah** | 🔵 Biru | Pelanggaran minor, kemungkinan tidak disengaja |
| 5-12 | **Sedang** | 🟡 Kuning | Perlu diperhatikan, mungkin ada masalah |
| 13+ | **Tinggi** | 🔴 Merah | Perlu review serius, kemungkinan kecurangan |

**Contoh:**
- Peserta A: 3× no_face (3×2=6) + 1× window_blur (1×3=3) = **Risk Score 9** (Sedang)
- Peserta B: 1× camera_stopped (1×5=5) + 2× multiple_faces (2×4=8) = **Risk Score 13** (Tinggi)

**Catatan:** Risk score adalah **indikator**, bukan bukti. Selalu review foto dan timeline sebelum mengambil keputusan.

---

## 1.7 Review Attempt

### Langkah-Langkah

1. Di halaman report, klik **"View detail"** pada attempt
2. Lihat **summary cards**: total events, violations, risk score
3. Lihat **event timeline**: grid foto-foto yang diambil
4. **Klik kartu event** untuk detail lengkap (metadata, waktu, durasi)
5. Isi **form review**: pilih status, tulis komentar
6. Klik **"Save review"**

![Attempt Detail](pix/guide/11.png)
![Review Form](pix/guide/13.png)

### Status Review

| Status | Keterangan |
|--------|-----------|
| **Pending** | Belum diputuskan (default) |
| **Cleared** | Dianggap aman, tidak ada masalah |
| **Suspicious** | Dicurigai kecurangan, perlu tindak lanjut |

---

## 1.8 Live Monitoring

### Apa itu Live Monitoring?

Live monitoring memungkinkan Anda **melihat webcam peserta secara langsung** selama mereka mengerjakan quiz.

### Prasyarat

1. LiveKit dikonfigurasi di Site administration (lihat 1.10)
2. Quiz settings → "Enable optional live monitoring" centang
3. Peserta sedang aktif mengerjakan quiz (badge Online)

### Langkah-Langkah

1. Buka **report** → klik **"Live Monitor"**
2. Pilih **mode filter** dari dropdown
3. Klik **"Start pilihan"** atau **"Start"** per tile
4. Tunggu status **"Tersambung"** → video muncul
5. Klik **"Stop"** atau tutup modal untuk berhenti

![Live Dashboard](pix/guide/14.png)

### Indikator Online/Offline

| Badge | Arti |
|-------|------|
| 🟢 **Online** | Peserta aktif — ada monitoring event dalam 60 detik terakhir |
| ⚪ **Offline** | Peserta tidak aktif — tidak ada event baru |

### Auto-Reorder

Tile otomatis diurutkan berdasarkan risk score (tertinggi di kiri atas). Urutan berubah setiap 4 detik. Video stream tetap jalan saat tile bergeser.

### Mode Filter

| Mode | Keterangan |
|------|-----------|
| Prioritas risiko | Risk score tertinggi |
| Hanya yang ada violation | Filter pelanggaran |
| Kamera bermasalah | Camera stopped/error |
| Belum pernah dicek | Belum dimonitor live |
| Risk tinggi/sedang/rendah | Filter level |
| Semua attempt aktif | Tampilkan semua |
| Acak 20 peserta | Pilih acak |

---

## 1.9 Kirim Info ke Peserta

### Per Peserta

1. Buka Live Monitor
2. Di setiap tile, ada **input teks** di bagian bawah
3. **Ketik pesan** → klik **"Send"**
4. Pesan muncul sebagai **overlay di layar peserta**

### Ke Semua

1. Di bagian **atas dashboard**, ada bar **"Kirim Info ke Semua"**
2. **Ketik pesan** → klik **"Kirim ke Semua"**
3. Terkirim ke **semua peserta yang tampil** (sesuai filter aktif)

**Tips:**
- Gunakan filter sebelum kirim ke semua
- Pesan yang jelas dan spesifik
- Info bersifat satu kali (peserta bisa dismiss)

---

## 1.10 Konfigurasi LiveKit (Global)

LiveKit diperlukan untuk fitur **live monitoring**.

### Langkah-Langkah

1. Buka https://cloud.livekit.io/ → buat akun → buat project
2. Catat **WebSocket URL**, **API Key**, **API Secret**
3. Di Moodle: **Site administration → Module settings → Webcam Guard**
4. Masukkan URL, Key, Secret
5. Atur **Token TTL** (default: 300 detik)
6. Klik **"Save changes"**

### Verifikasi

Setelah konfigurasi, buka quiz settings → pastikan "Enable optional live monitoring" bisa dicentang.

---

## 1.11 Konfigurasi Data Retention (Global)

### Apa itu Data Retention?

Data retention mengatur **berapa lama data monitoring disimpan** sebelum dihapus otomatis. Termasuk: event, snapshot, review, data live monitoring.

### Cara Mengatur

1. **Site administration → Module settings → Webcam Guard**
2. Cari bagian **"Data retention"**
3. Pilih periode: **7 / 14 / 30 / 60 / 90 / 180 / 365 hari**
4. Klik **"Save changes"**

**Default: 30 hari.** Data yang sudah dihapus tidak bisa dikembalikan.

---

## 1.12 Troubleshooting Admin/Trainer

| Masalah | Solusi |
|---------|--------|
| Report kosong | Cek Webcam Guard enabled, ada peserta mulai attempt, cek filter |
| Snapshot tidak muncul | Cek "Capture snapshot on violation", peserta pakai HTTPS |
| Live monitoring tidak bisa | Cek LiveKit config, quiz settings live monitoring, capability |
| Start live tidak nyambung | Pastikan peserta Online (badge hijau), cek LiveKit config |
| Risk score 0 tapi ada pelanggaran | Cek threshold, cek event timeline |
| Banyak false positive | Naikkan threshold (terutama no-face dan blur) |
| Peserta gagal verifikasi identitas | Naikkan identity threshold, atau ganti ke Flag mode |

---

# Bagian 2: Untuk Peserta

## 2.1 Apa itu Webcam Guard?

Webcam Guard adalah sistem pengawasan otomatis untuk quiz online:

- **Webcam aktif** selama mengerjakan quiz
- **Wajah dipantau** setiap 1 detik
- **Foto diambil** saat terdeteksi pelanggaran
- **Aktivitas dicatat** sebagai bukti untuk review trainer
- **Trainer bisa mengirim info** langsung ke layar Anda

**TIDAK dilakukan:** Tidak merekam video, tidak mengakses file, tidak mengambil alih layar, tidak memblokir quiz.

---

## 2.2 Persiapan Sebelum Quiz

| No | Persiapan | Cara Mengecek |
|----|-----------|---------------|
| 1 | **Webcam terpasang** | Buka aplikasi Camera |
| 2 | **Browser terbaru** | Chrome/Edge 90+, Firefox 90+, Safari 15+ |
| 3 | **Koneksi stabil** | WiFi/kabel tidak putus-putus |
| 4 | **Pencahayaan cukup** | Wajah terlihat jelas, hindari backlight |
| 5 | **Tutup aplikasi webcam lain** | Tutup Zoom, Teams, Google Meet |
| 6 | **Foto profil Moodle** | Upload jika diminta verifikasi identitas |

**Di HP:** Gunakan kamera depan, browser terbaru, posisi tegak, pencahayaan dari depan.

---

## 2.3 Langkah-Langkah Memulai Quiz

**Langkah 1:** Buka quiz, klik **"Attempt quiz"**.

**Langkah 2:** Jendela pemeriksaan webcam muncul.

![Preflight](pix/guide/06.png)

**Langkah 3:** Centang **checkbox persetujuan**.

**Langkah 4:** Klik **"Periksa webcam"** → browser minta izin → klik **"Izinkan"**.

**Langkah 5:** Preview kamera muncul. Pastikan wajah terlihat jelas.

**Langkah 6:** Jika verifikasi identitas aktif, tunggu hasil:
- 🟢 **Hijau** = cocok
- 🔴 **Merah** = tidak cocok

![Identity](pix/guide/07.png)

**Langkah 7:** Klik **"Start attempt"** untuk mulai quiz.

---

## 2.4 Verifikasi Identitas

Jika trainer mengaktifkan verifikasi identitas:

| Status | Border | Arti |
|--------|--------|------|
| Mencari | 🟣 Ungu berkedip | Sedang mencari wajah |
| Cocok | 🟢 Hijau | Wajah cocok foto profil |
| Tidak cocok | 🔴 Merah | Wajah tidak cocok |

- **Mode Flag**: Tetap bisa mulai, ditandai di report
- **Mode Block**: Harus cocok dulu sebelum bisa mulai

---

## 2.5 Selama Mengerjakan Quiz

### Yang Dipantau

| Apa | Pelanggaran | Contoh |
|-----|-------------|--------|
| Wajah tidak terlihat | > 10 detik* | Melihat ke bawah lama |
| Banyak wajah | > 3 detik* | Ada orang lain |
| Pindah tab | > 5 detik* | Buka aplikasi lain |
| Kamera mati | Langsung | Tutup kamera |

*Threshold bisa diatur trainer, jadi bisa berbeda per quiz.

![Camera Preview](pix/guide/08.png)

Preview kamera kecil di pojok kanan bawah. Tidak mengganggu pengerjaan quiz.

---

## 2.6 Menerima Info dari Trainer

![Info Overlay](pix/guide/16.png)

- Info muncul sebagai **overlay full-screen**
- **Klik di mana saja** atau **tunggu 30 detik** untuk menutup
- Info bersifat **satu kali**
- Baca pesan → perbaiki perilaku → lanjutkan quiz

---

## 2.7 Menyelesaikan Quiz

1. Klik **"Finish attempt"**
2. Konfirmasi
3. Webcam **otomatis berhenti**
4. Data **tersimpan otomatis**

---

## 2.8 Yang Boleh dan Tidak Boleh

**✅ Boleh:** Quiz biasa, lihat ke bawah, minum air, posisi normal, kacamata.

**❌ Tidak boleh:** Pindah tab, tutup kamera, ada orang lain, tutup browser, virtual background.

**💡 Tips:** Chrome/Edge terbaik. Di HP: kamera depan. Pencahayaan dari depan. Jarak 30-50cm.

---

## 2.9 Troubleshooting Peserta

| Masalah | Solusi |
|---------|--------|
| Webcam tidak ditemukan | Cek webcam, tutup Zoom/Teams, cek izin browser, refresh |
| Izin kamera ditolak | Klik icon address bar, ubah ke Izinkan, refresh |
| Identity tidak cocok | Upload foto profil, pencahayaan cukup, wajah di tengah |
| Preview tidak muncul | Cek koneksi, coba Chrome, restart browser |
| Info muncul terus | Baca pesan, perbaiki perilaku |

---

# FAQ

**Q: Apakah webcam direkam?**
A: Tidak, hanya foto saat pelanggaran.

**Q: Berapa lama data disimpan?**
A: Default 30 hari, bisa diatur 7-365 hari.

**Q: Bisa mengerjakan di HP?**
A: Ya, Android/iOS. Gunakan kamera depan.

**Q: Apakah setting bisa diubah setelah quiz?**
A: Bisa, tapi hanya berlaku untuk attempt baru.

**Q: Berapa max peserta live monitoring?**
A: 20 per halaman, ada navigasi.

**Q: Perlu LiveKit?**
A: Hanya untuk live monitoring. Fitur lain tanpa LiveKit.

**Q: Risk score tinggi = kecurangan?**
A: Tidak. Risk score indikator, bukan bukti. Selalu review foto.
