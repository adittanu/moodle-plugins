# Dali AI Plugins — Panduan Admin

Panduan konfigurasi dan administrasi untuk **Admin Moodle**.

Plugin yang dicakup:
- **Dali AI Widget** (`local_daliwidget`) — Floating AI chat assistant + Knowledge Base
- **AI Quiz Generator** (`local_aiquizgen`) — Generate soal otomatis dengan AI
- **AI Grading** (`local_aigrading`) — Koreksi essay otomatis dengan AI
- **AI Lesson Plan** (`local_ailessonplan`) — Generate rencana pembelajaran dengan AI

Versi 1.0.0

---

## Daftar Isi

- [Bagian 1: Dali AI Widget — Konfigurasi Admin](#bagian-1-dali-ai-widget--konfigurasi-admin)
  - [1.1 Overview](#11-overview)
  - [1.2 Konfigurasi Global Settings](#12-konfigurasi-global-settings)
  - [1.3 Global Knowledge Base](#13-global-knowledge-base)
  - [1.4 Signed URL (Advanced)](#14-signed-url-advanced)
  - [1.5 Troubleshooting Admin](#15-troubleshooting-admin)
- [Bagian 2: AI Quiz Generator — Konfigurasi Admin](#bagian-2-ai-quiz-generator--konfigurasi-admin)
  - [2.1 Overview](#21-overview)
  - [2.2 Konfigurasi Settings](#22-konfigurasi-settings)
  - [2.3 Troubleshooting Admin](#23-troubleshooting-admin)
- [Bagian 3: AI Grading — Konfigurasi Admin](#bagian-3-ai-grading--konfigurasi-admin)
  - [3.1 Overview](#31-overview)
  - [3.2 Konfigurasi Settings](#32-konfigurasi-settings)
  - [3.3 Troubleshooting Admin](#33-troubleshooting-admin)
- [Bagian 4: AI Lesson Plan — Konfigurasi Admin](#bagian-4-ai-lesson-plan--konfigurasi-admin)
  - [4.1 Overview](#41-overview)
  - [4.2 Konfigurasi Settings](#42-konfigurasi-settings)
  - [4.3 Troubleshooting Admin](#43-troubleshooting-admin)
- [FAQ Admin](#faq-admin)

---

# Bagian 1: Dali AI Widget — Konfigurasi Admin

## 1.1 Overview

Dali AI Widget menambahkan **floating AI chat assistant** di semua halaman Moodle (kecuali quiz). Admin bertanggung jawab untuk:

- Mengkonfigurasi koneksi ke Dali AI backend
- Mengelola **Global Knowledge Base** (tanpa course scope)
- Mengatur **Signed URL** jika diperlukan
- Mengaktifkan/menonaktifkan **Debug Mode** untuk troubleshooting

### Arsitektur

```
+-------------------------------------------------+
|  Admin Settings (Global)                        |
|  - API Key, Base URL, Sync Mode                 |
|  - Strict Course Mode, Debug Mode               |
+------------------+------------------------------+
                   |
       +-----------v-----------+
       |  Knowledge Base       |
       |  - Global (Admin)     |
       |  - Per Course (Guru)  |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  Widget Chat          |
       |  (floating di semua   |
       |   halaman Moodle)     |
       +-----------------------+
```

---

## 1.2 Konfigurasi Global Settings

### Cara Mengakses

1. Login ke Moodle sebagai admin
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **Dali AI Widget**
3. Atur setting sesuai kebutuhan
4. Klik **"Save changes"**

![Dali Widget Settings](pix/guide/daliwidget-settings.png)

---

### [v] Enable Widget

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Aktif (dicentang) |
| **Fungsi** | Mengaktifkan atau menonaktifkan widget chat di seluruh situs |

**Penjelasan:**
- Jika **dicentang**: Widget chat AI akan muncul di semua halaman Moodle (kecuali halaman quiz). Peserta dan trainer bisa langsung chat dengan AI.
- Jika **tidak dicentang**: Widget tidak muncul sama sekali. Semua fitur chat dan knowledge base tetap tersimpan, tapi tidak bisa digunakan.

**Kapan digunakan:** Selalu aktifkan kecuali Anda ingin menonaktifkan sementara (misalnya maintenance).

---

###  API Key

| | |
|---|---|
| **Tipe** | Password |
| **Default** | Kosong |
| **Fungsi** | Bearer token untuk autentikasi ke Dali AI backend |

**Penjelasan:**
API Key digunakan untuk mengautentikasi koneksi antara Moodle dan Dali AI application. Tanpa API Key yang valid, widget tidak bisa mengirim atau menerima pesan.

**Cara mendapatkan:**
1. Buka Dali AI dashboard
2. Masuk ke **My Agents** -> **Manage** -> **API Key**
3. Copy API Key
4. Paste ke field ini

**Tips:** API Key bersifat rahasia. Jangan bagikan ke peserta atau publik. Jika Anda mencurigai API Key sudah bocor, generate yang baru di Dali dashboard.

---

### [web] Base URL

| | |
|---|---|
| **Tipe** | Text |
| **Default** | `https://dali-app.test` |
| **Fungsi** | URL root dari aplikasi Dali AI |

**Penjelasan:**
Ini adalah URL dasar tempat Dali AI berjalan. Plugin akan mengirim request ke `{Base URL}/api/...` untuk semua operasi.

**Contoh:**
| Environment | Base URL |
|-------------|----------|
| Local development | `http://localhost:8000` |
| Staging | `https://staging.dali.example.com` |
| Production | `https://dali.example.com` |

**Tips:** Pastikan URL bisa diakses dari server Moodle. Jika Moodle dan Dali berbeda server, pastikan firewall mengizinkan koneksi.

---

### [file] Max upload size (MB)

| | |
|---|---|
| **Tipe** | Angka (integer) |
| **Default** | 20 |
| **Range** | 1 - server limit |
| **Fungsi** | Ukuran maksimum file yang bisa diupload ke Knowledge Base |

**Penjelasan:**
Ini adalah batas ukuran file untuk upload dokumen ke Dali Knowledge Sync. Nilai ini harus **sama dengan atau lebih kecil** dari upload limit di server Dali AI.

**Rekomendasi:**
| Jenis Dokumen | Ukuran Rata-rata | Rekomendasi |
|---------------|------------------|-------------|
| PDF teks | 0.5-5 MB | 20 MB cukup |
| PDF dengan gambar | 5-20 MB | 20-50 MB |
| Video (MP4) | 50-500 MB | Sesuaikan dengan kebutuhan |

**Tips:** Jika upload gagal dengan error "file too large", cek apakah nilai ini lebih kecil dari `upload_max_filesize` di php.ini server Dali.

---

### [fast] Sync Mode

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Asynchronous (background task) |
| **Pilihan** | Synchronous (langsung), Asynchronous (background task) |
| **Fungsi** | Menentukan cara file dikirim ke Dali API |

**Penjelasan:**

**Asynchronous (recommended):**
- Request sync dijadwalkan ke Moodle background task (cron)
- File besar tidak memblokir halaman web
- User bisa langsung melanjutkan bekerja
- Status sync bisa dicek di halaman Knowledge Base

**Synchronous:**
- Request sync langsung diproses saat itu juga
- Cocok untuk file kecil dan testing
- Bisa memblokir halaman jika file besar

**Rekomendasi:** Selalu gunakan **Asynchronous** untuk production. Gunakan Synchronous hanya untuk testing atau file sangat kecil (< 1 MB).

---

### [dart] Strict Course Mode

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif |
| **Fungsi** | Membatasi AI hanya menjawab dari materi kursus |

**Penjelasan:**
- Jika **tidak dicentang**: AI bisa menjawab pertanyaan umum, bahkan di luar konteks kursus. Cocok untuk trainer yang ingin AI lebih fleksibel.
- Jika **dicentang**: AI **HANYA** akan menjawab pertanyaan berdasarkan materi kursus yang sudah disinkronkan. Pertanyaan di luar cakupan kursus akan ditolak secara sopan.

**Kapan digunakan:**
| Mode | Cocok untuk |
|------|-------------|
| **Nonaktif** | Training umum, brainstorming, tanya jawab bebas |
| **Aktif** | Pembelajaran fokus, ujian, materi spesifik kursus |

**Tips:** Jika diaktifkan, pastikan Knowledge Base kursus sudah diisi dengan materi yang cukup. AI tidak bisa menjawab jika tidak ada konteks.

---

### [bug] Debug Mode

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif |
| **Fungsi** | Mengaktifkan logging debug untuk troubleshooting |

**Penjelasan:**
- Jika **tidak dicentang**: Widget berjalan normal tanpa informasi debug.
- Jika **dicentang**: Informasi debug akan muncul di:
  - **Browser console** (tekan F12 -> tab Console)
  - **Debug panel** di halaman (muncul di bagian bawah)

**Apa yang ditampilkan:**
- API URL yang dipanggil
- API Key (10 karakter pertama)
- Request dan response JSON
- Status HTTP
- Timestamp

**Kapan digunakan:** Aktifkan hanya saat troubleshooting. Nonaktifkan di production karena bisa memperlambat halaman dan menampilkan informasi sensitif.

---

### Ringkasan Setting Rekomendasi

**Untuk Setup Awal:**
| Setting | Nilai |
|---------|-------|
| Enable Widget | [v] |
| API Key | Isi dari Dali dashboard |
| Base URL | Sesuaikan environment |
| Max upload size | 20 MB |
| Sync Mode | Asynchronous |
| Strict Course Mode | [x] |
| Debug Mode | [x] |

**Untuk Production (Pembelajaran Fokus):**
| Setting | Nilai |
|---------|-------|
| Enable Widget | [v] |
| Strict Course Mode | [v] |
| Debug Mode | [x] |
| Sync Mode | Asynchronous |

---

## 1.3 Global Knowledge Base

### Apa itu Global Knowledge Base?

Global Knowledge Base adalah knowledge base yang **tidak terikat ke course tertentu**. Cocok untuk:
- Informasi umum institusi
- FAQ umum
- Dokumen kebijakan
- Materi lintas kursus

### Cara Mengakses

1. Login sebagai **admin**
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **Dali AI Widget** -> **Global Knowledge Base**
3. Atau: Menu admin -> Local plugins -> Global Knowledge Base

### Perbedaan dengan Knowledge Base per Course

| Aspek | Per Course | Global |
|-------|-----------|--------|
| **Scope** | Hanya untuk 1 course | Seluruh situs |
| **Siapa yang kelola** | Trainer + Admin | Hanya Admin |
| **Konteks AI** | Saat user di course tersebut | Saat user di halaman tanpa course |
| **Contoh** | Materi kuliah, RPS, slide | Kebijakan kampus, FAQ umum |

**Tips:** Gunakan Global Knowledge Base untuk informasi yang relevan di semua course. Gunakan Knowledge Base per course untuk materi spesifik.

### Upload Dokumen

Format yang didukung:
| Format | Ekstensi | Keterangan |
|--------|----------|------------|
| PDF | `.pdf` | Dokumen PDF teks atau gambar |
| Word | `.docx` | Dokumen Microsoft Word |
| Text | `.txt` | File teks biasa |
| PowerPoint | `.pptx` | Presentasi PowerPoint |

---

## 1.4 Signed URL (Advanced)

### Apa itu Signed URL?

Signed URL adalah mekanisme di mana Moodle mengirim **URL sementara yang di-sign** ke Dali API, bukan mengupload file langsung. Dali API kemudian mendownload file dari URL tersebut.

### Kapan Menggunakan?

| Skenario | Gunakan Signed URL? |
|----------|-------------------|
| Moodle dan Dali di server berbeda | [x] (binary upload lebih aman) |
| Moodle dan Dali di server sama | [v] (lebih efisien) |
| Menggunakan Cloudflare Tunnel | [v] (isi Signed URL base URL) |
| File sangat besar (> 50 MB) | [v] (menghindari timeout upload) |

### Konfigurasi

| Setting | Tipe | Default | Fungsi |
|---------|------|---------|--------|
| **Enable signed URL file sync** | Checkbox | Nonaktif | Aktifkan signed URL workflow |
| **Signed download secret** | Password | Kosong | HMAC secret untuk sign URL |
| **Signed URL base URL** | URL | Kosong | Override base URL (opsional) |

### Cara Setup

1. Aktifkan **"Enable signed URL file sync"**
2. Isi **"Signed download secret"** dengan string acak minimal 32 karakter
3. (Opsional) Isi **"Signed URL base URL"** jika menggunakan tunnel/reverse proxy
4. Klik **"Save changes"**

### Cara Kerja

```
Moodle Plugin                    Dali API
     |                              |
     |  1. Generate signed URL      |
     |     (HMAC-SHA256)            |
     |                              |
     |  2. Send URL + signature ---->
     |                              |
     |                    3. Verify signature
     |                    4. Download file
     |                    5. Process file
     |                              |
     |  <-- 6. Return result -------|
```

**Tips:** Jika Dali API menolak signed URL, plugin otomatis fallback ke binary upload. Tidak perlu khawatir jika setup salah.

---

## 1.5 Troubleshooting Admin

### Widget Tidak Muncul

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| Widget dinonaktifkan | Cek setting "Enable Widget" |
| Halaman quiz | Widget memang tidak muncul di quiz |
| JavaScript error | Buka F12 -> Console, cek error |
| Theme conflict | Coba ganti theme ke default |

### AI Tidak Merespons

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| API Key salah | Cek setting "API Key" |
| Base URL salah | Cek setting "Base URL" |
| Dali API down | Cek apakah Dali API bisa diakses |
| Network issue | Pastikan server Moodle bisa akses Dali API |
| CORS issue | Cek konfigurasi CORS di Dali API |

### Knowledge Base Tidak Ter-sync

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| Mode Sync salah | Coba ganti ke Synchronous |
| File terlalu besar | Cek "Max upload size MB" |
| Cron tidak jalan | Pastikan Moodle cron berjalan |
| API Key tidak valid | Cek setting "API Key" |

### Debug Mode untuk Troubleshooting

1. Aktifkan **Debug Mode** di settings
2. Buka halaman yang bermasalah
3. Klik widget chat -> kirim pesan
4. Cek **debug panel** di bagian bawah halaman
5. Buka **F12** -> tab **Console** untuk log detail
6. Catat error yang muncul

---

# Bagian 2: AI Quiz Generator — Konfigurasi Admin

## 2.1 Overview

AI Quiz Generator memungkinkan trainer **generate soal quiz secara otomatis** menggunakan AI. Admin bertanggung jawab untuk:

- Mengkonfigurasi koneksi ke Dali AI backend
- Mengatur batas jumlah soal per request
- Mengaktifkan logging untuk audit

---

## 2.2 Konfigurasi Settings

### Cara Mengakses

1. Login ke Moodle sebagai admin
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **AI Quiz Generator**
3. Atur setting sesuai kebutuhan
4. Klik **"Save changes"**

---

### [web] Dali API Base URL

| | |
|---|---|
| **Tipe** | Text |
| **Default** | `http://localhost:8000` |
| **Fungsi** | URL dasar dari Dali AI service |

**Penjelasan:**
Sama seperti Base URL di Dali Widget. Ini adalah alamat server Dali AI yang akan memproses permintaan generate soal.

**Contoh:**
| Environment | URL |
|-------------|-----|
| Local | `http://localhost:8000` |
| Production | `https://dali.example.com` |

---

###  Dali API Key

| | |
|---|---|
| **Tipe** | Password |
| **Default** | Kosong |
| **Fungsi** | API Key untuk autentikasi ke Dali AI |

**Penjelasan:**
Sama seperti API Key di Dali Widget. Bisa menggunakan API Key yang sama jika Dali Widget sudah dikonfigurasi.

---

###  Test Connection

| | |
|---|---|
| **Tipe** | Button |
| **Fungsi** | Menguji koneksi ke Dali API |

**Penjelasan:**
Klik tombol ini untuk memverifikasi bahwa plugin bisa terhubung ke Dali API. Jika berhasil, akan muncul pesan hijau. Jika gagal, akan muncul pesan error dengan detail.

**Pesan yang mungkin muncul:**
| Pesan | Arti |
|-------|------|
| [v] Connection successful! | Koneksi berhasil |
| [x] API key is not configured | Isi API Key dulu |
| [x] Connection failed: Invalid API key | API Key salah |
| [x] Connection failed: HTTP error xxx | Masalah server |

---

### [chart] Maximum Questions per Request

| | |
|---|---|
| **Tipe** | Angka (integer) |
| **Default** | 20 |
| **Fungsi** | Batas maksimum soal yang bisa di-generate dalam sekali request |

**Penjelasan:**
Ini adalah batas atas untuk field "Number of Questions" di form generate. Trainer tidak bisa memilih lebih dari angka ini.

**Rekomendasi:** 20 sudah cukup untuk kebanyakan kasus. Naikkan jika trainer sering generate soal dalam jumlah besar.

---

### [edit] Enable Logging

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Aktif (dicentang) |
| **Fungsi** | Mencatat semua aktivitas generate soal |

**Penjelasan:**
Jika dicentang, setiap aktivitas generate soal akan dicatat: siapa yang generate, topik apa, berapa soal, kapan. Berguna untuk audit.

---

## 2.3 Troubleshooting Admin

### API Key Tidak Dikonfigurasi

| Error | "API key is not configured" |
|-------|---------------------------|
| **Solusi** | Buka Site admin -> Plugins -> Local plugins -> AI Quiz Generator -> isi API Key |

### Koneksi Gagal

| Error | "Connection failed" |
|-------|-------------------|
| **Solusi** | 1. Cek Base URL di settings. 2. Buka Base URL di browser — harusnya ada response. 3. Cek firewall/network. |

---

# Bagian 3: AI Grading — Konfigurasi Admin

## 3.1 Overview

AI Grading memungkinkan trainer **koreksi essay otomatis** menggunakan AI. Admin bertanggung jawab untuk:

- Mengkonfigurasi koneksi ke Dali AI backend
- Mengatur **rubrik default** dan **system prompt**
- Mengkonfigurasi **batch processing**
- Mengisi **Usage Guide** untuk trainer

---

## 3.2 Konfigurasi Settings

### Cara Mengakses

1. Login ke Moodle sebagai admin
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **AI Grading**
3. Atur setting sesuai kebutuhan
4. Klik **"Save changes"**

---

### Group 1: API Settings

#### [web] Dali API Base URL

| | |
|---|---|
| **Tipe** | Text |
| **Default** | `http://localhost:8000` |
| **Fungsi** | URL dasar dari Dali AI service |

####  Dali API Key

| | |
|---|---|
| **Tipe** | Password |
| **Default** | Kosong |
| **Fungsi** | API Key untuk autentikasi |

####  Test Connection

| | |
|---|---|
| **Tipe** | Button |
| **Fungsi** | Menguji koneksi ke Dali API |

---

### Group 2: Usage Guide

Bagian ini berisi **tips dan contoh** untuk trainer tentang cara mendapatkan hasil AI grading terbaik. Tips utama:

**Isi "Information for graders" saat membuat soal essay!**

Ini membantu AI memahami jawaban yang benar. Cara mengisinya:
1. Buka **Question Bank** -> Edit soal Essay
2. Scroll ke bagian **"Grader information"**
3. Isi **"Information for graders"** dengan:
   - Jawaban yang benar
   - Poin-poin penilaian
   - Kriteria khusus

**Contoh untuk soal fakta:**
```
Correct Answer:
OpenAI is an AI research company founded in 2015 by Sam Altman, 
Elon Musk, and others. OpenAI created ChatGPT, GPT-4, and DALL-E.

Grading Points:
- Mentions OpenAI is an AI company (2 points)
- Mentions founding year 2015 or founders (1 point)
- Mentions products like ChatGPT/GPT (2 points)
```

**Contoh untuk essay argumentatif:**
```
Grading Criteria:
1. Clear introduction with thesis statement (2 points)
2. At least 3 supporting arguments with examples (3 points)
3. Use of relevant references/sources (2 points)
4. Conclusion that summarizes arguments (2 points)
5. Good grammar and paragraph structure (1 point)
```

**Tips:** Jika "Information for graders" kosong, AI akan menilai berdasarkan kriteria umum (struktur, koherensi, bahasa). Confidence level akan lebih rendah.

---

### Group 3: Grading Settings

#### [list] Default Rubric

| | |
|---|---|
| **Tipe** | Textarea |
| **Default** | Rubrik 4 tier (Indonesian) |
| **Fungsi** | Rubrik penilaian default |

**Rubrik default:**
```
Kriteria penilaian:
- 90-100: Jawaban lengkap, contoh relevan, penjelasan jelas dan terstruktur
- 70-89: Jawaban cukup lengkap, ada beberapa kekurangan minor
- 50-69: Jawaban kurang lengkap, perlu perbaikan signifikan
- <50: Jawaban tidak memenuhi kriteria minimum atau tidak relevan
```

**Tips:** Kustomisasi rubrik ini sesuai standar institusi Anda. Rubrik yang lebih spesifik menghasilkan penilaian yang lebih akurat.

#### [msg] System Prompt

| | |
|---|---|
| **Tipe** | Textarea |
| **Default** | Prompt Indonesia untuk grading essay |
| **Fungsi** | Template prompt untuk AI |

**Penjelasan:**
System prompt adalah instruksi untuk AI tentang cara menilai essay. Default sudah dikonfigurasi untuk:
- Validasi relevansi jawaban
- Format output JSON (grade, feedback, explanation)
- Penilaian berdasarkan rubrik

**Tips:** Hanya modifikasi jika Anda tahu apa yang Anda lakukan. Perubahan yang salah bisa membuat AI memberikan nilai yang tidak akurat.

---

### Group 4: Batch Processing Settings

#### [fast] Enable Background Task

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Nonaktif |
| **Fungsi** | Menjalankan bulk grading sebagai background task |

**Penjelasan:**
- Jika **tidak dicentang**: Bulk grading berjalan sinkronus dengan progress update real-time.
- Jika **dicentang**: Bulk grading dijadwalkan ke Moodle cron. Cocok untuk jumlah essay sangat banyak.

#### [chart] Batch Size

| | |
|---|---|
| **Tipe** | Angka (integer) |
| **Default** | 10 |
| **Fungsi** | Jumlah essay per batch |

**Rekomendasi:** 10-20. Terlalu besar bisa timeout, terlalu kecil lambat.

#### [sync] Max Retries per Batch

| | |
|---|---|
| **Tipe** | Angka (integer) |
| **Default** | 3 |
| **Fungsi** | Jumlah percobaan ulang jika batch gagal |

---

## 3.3 Troubleshooting Admin

### API Key Tidak Dikonfigurasi

| Error | "Dali API key is not configured" |
|-------|--------------------------------|
| **Solusi** | Buka Site admin -> Plugins -> Local plugins -> AI Grading -> isi API Key |

### Koneksi Gagal

| Error | "Dali API error" |
|-------|-----------------|
| **Solusi** | 1. Cek Base URL. 2. Klik Test Connection. 3. Cek network/firewall. |

### Bulk Grading Timeout

| Masalah | Bulk grading berhenti di tengah jalan |
|---------|--------------------------------------|
| **Solusi** | 1. Kurangi Batch Size (misal: 5). 2. Aktifkan Background Task. 3. Cek timeout server. |

---

# Bagian 4: AI Lesson Plan — Konfigurasi Admin

## 4.1 Overview

AI Lesson Plan memungkinkan trainer **generate rencana pembelajaran** secara otomatis menggunakan AI. Admin bertanggung jawab untuk:

- Mengkonfigurasi koneksi ke Dali AI backend
- Mengaktifkan **saved draft history**

---

## 4.2 Konfigurasi Settings

### Cara Mengakses

1. Login ke Moodle sebagai admin
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **AI Lesson Plan**
3. Atur setting sesuai kebutuhan
4. Klik **"Save changes"**

---

### [web] Dali API Base URL

| | |
|---|---|
| **Tipe** | Text |
| **Default** | `http://localhost:8000` |
| **Fungsi** | URL dasar dari Dali AI service |

---

###  Dali API Key

| | |
|---|---|
| **Tipe** | Password |
| **Default** | Kosong |
| **Fungsi** | API Key untuk autentikasi |

---

### [edit] Enable saved draft history

| | |
|---|---|
| **Tipe** | Checkbox |
| **Default** | Aktif (dicentang) |
| **Fungsi** | Menyimpan lesson plan di Moodle |

**Penjelasan:**
- Jika **dicentang**: Setiap lesson plan yang di-generate akan disimpan di database Moodle. Trainer bisa membuka, mengedit, dan mempublish ulang nanti.
- Jika **tidak dicentang**: Lesson plan tidak disimpan. Setelah halaman ditutup, data hilang.

**Tips:** Selalu aktifkan ini agar trainer punya riwayat lesson plan.

---

## 4.3 Troubleshooting Admin

### API Key Tidak Dikonfigurasi

| Error | "Dali API key is not configured" |
|-------|--------------------------------|
| **Solusi** | Buka Site admin -> Plugins -> Local plugins -> AI Lesson Plan -> isi API Key |

### Generate Gagal

| Error | "AI service error" |
|-------|-------------------|
| **Solusi** | 1. Cek Base URL dan API Key. 2. Klik Test Connection (jika ada). 3. Cek Dali API logs. |

---

# FAQ Admin

### Q: Apakah semua plugin harus dikonfigurasi?

**A:** Tidak wajib. Setiap plugin independen. Anda bisa mengaktifkan hanya plugin yang dibutuhkan. Misalnya, jika hanya butuh AI Grading, cukup konfigurasi plugin AI Grading.

### Q: Bolehkah menggunakan API Key yang sama untuk semua plugin?

**A:** Ya, boleh. Semua plugin terhubung ke Dali AI yang sama, jadi bisa menggunakan API Key yang sama.

### Q: Apakah data peserta aman?

**A:** Data diproses melalui Dali AI backend. Pastikan Dali backend Anda aman dan sesuai kebijakan privasi institusi. Plugin tidak mengirim data ke pihak ketiga selain Dali AI.

### Q: Bagaimana jika Dali API down?

**A:** Semua fitur AI akan tidak tersedia. Moodle tetap berjalan normal. Widget chat akan menampilkan error. Soal/grading yang sudah di-generate tetap tersimpan.

### Q: Plugin mana yang harus diinstall duluan?

**A:** **Dali AI Widget** harus diinstall duluan jika trainer ingin menggunakan Knowledge Base dan Activity Sync. Plugin lain (aiquizgen, aigrading, ailessonplan) bisa diinstall independen.

### Q: Apakah bisa digunakan di Moodle 4.x dan 5.x?

**A:** Ya. Plugin dikembangkan untuk kompatibel dengan Moodle 4.1+ dan Moodle 5.x.

### Q: Bagaimana cara update plugin?

**A:** 1. Download versi terbaru dari repository. 2. Copy ke folder `local/` di Moodle. 3. Buka Moodle -> Notifications -> klik "Upgrade Moodle database". 4. Konfigurasi ulang jika ada setting baru.

---

*Dokumentasi ini dibuat untuk Dali AI Plugins v1.0.0. Untuk panduan penggunaan oleh trainer, lihat `GUIDE_TEACHER_DALI_PLUGINS.md`.*
