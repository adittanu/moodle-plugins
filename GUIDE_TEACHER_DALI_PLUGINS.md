# Dali AI Plugins — Panduan Trainer/Guru

Panduan penggunaan untuk **Trainer** dan **Guru**.

Plugin yang dicakup:
- **Dali AI Widget** (`local_daliwidget`) — Floating AI chat assistant + Knowledge Base
- **AI Quiz Generator** (`local_aiquizgen`) — Generate soal otomatis dengan AI
- **AI Grading** (`local_aigrading`) — Koreksi essay otomatis dengan AI
- **AI Lesson Plan** (`local_ailessonplan`) — Generate rencana pembelajaran dengan AI

Versi 1.0.0

> **Catatan:** Panduan ini mengasumsikan admin sudah mengkonfigurasi API Key dan Base URL untuk semua plugin. Jika fitur AI tidak tersedia, hubungi admin Moodle Anda.

---

## Daftar Isi

- [Bagian 1: Dali AI Widget — Penggunaan Trainer](#bagian-1-dali-ai-widget--penggunaan-trainer)
  - [1.1 Apa itu Dali AI Widget?](#11-apa-itu-dali-ai-widget)
  - [1.2 Knowledge Base per Course](#12-knowledge-base-per-course)
  - [1.3 Menggunakan Widget Chat](#13-menggunakan-widget-chat)
  - [1.4 Troubleshooting](#14-troubleshooting)
- [Bagian 2: AI Quiz Generator — Generate Soal](#bagian-2-ai-quiz-generator--generate-soal)
  - [2.1 Apa itu AI Quiz Generator?](#21-apa-itu-ai-quiz-generator)
  - [2.2 Generate Soal dari Topic](#22-generate-soal-dari-topic)
  - [2.3 Generate Soal dari PDF](#23-generate-soal-dari-pdf)
  - [2.4 Generate Soal dari Knowledge Source](#24-generate-soal-dari-knowledge-source)
  - [2.5 Penjelasan Lengkap Setiap Field Form](#25-penjelasan-lengkap-setiap-field-form)
  - [2.6 Preview dan Save ke Question Bank](#26-preview-dan-save-ke-question-bank)
  - [2.7 Troubleshooting](#27-troubleshooting)
- [Bagian 3: AI Grading — Koreksi Essay](#bagian-3-ai-grading--koreksi-essay)
  - [3.1 Apa itu AI Grading?](#31-apa-itu-ai-grading)
  - [3.2 Suggest Grade (Satu per Satu)](#32-suggest-grade-satu-per-satu)
  - [3.3 Bulk AI Grade All](#33-bulk-ai-grade-all)
  - [3.4 Auto AI Grade](#34-auto-ai-grade)
  - [3.5 Auto Grade ALL Questions](#35-auto-grade-all-questions)
  - [3.6 Memahami Confidence Level](#36-memahami-confidence-level)
  - [3.7 Tips Mendapatkan Hasil Terbaik](#37-tips-mendapatkan-hasil-terbaik)
  - [3.8 Troubleshooting](#38-troubleshooting)
- [Bagian 4: AI Lesson Plan — Generate Rencana Pembelajaran](#bagian-4-ai-lesson-plan--generate-rencana-pembelajaran)
  - [4.1 Apa itu AI Lesson Plan?](#41-apa-itu-ai-lesson-plan)
  - [4.2 Generate Lesson Plan](#42-generate-lesson-plan)
  - [4.3 Penjelasan Lengkap Setiap Field Form](#43-penjelasan-lengkap-setiap-field-form)
  - [4.4 Preview dan Edit Plan](#44-preview-dan-edit-plan)
  - [4.5 Publish ke Moodle Course](#45-publish-ke-moodle-course)
  - [4.6 Download JSON](#46-download-json)
  - [4.7 Troubleshooting](#47-troubleshooting)
- [FAQ Trainer](#faq-trainer)

---

# Bagian 1: Dali AI Widget — Penggunaan Trainer

## 1.1 Apa itu Dali AI Widget?

Dali AI Widget adalah **floating AI chat assistant** yang muncul di pojok kanan bawah halaman Moodle. Sebagai trainer, Anda bisa:

- **Chat dengan AI** yang tahu konteks halaman, course, dan activity yang sedang Anda buka
- **Mengelola Knowledge Base** per course — upload dokumen, tambah web link, YouTube video
- **Sync activity content** — otomatis konten dari aktivitas kursus ke Knowledge Base

### Akses Trainer

| Fitur | Akses |
|-------|-------|
| Widget Chat | [v] Bisa digunakan |
| Knowledge Base per Course | [v] Bisa kelola |
| Global Knowledge Base | [x] Hanya admin |
| Konfigurasi Settings | [x] Hanya admin |

---

## 1.2 Knowledge Base per Course

### Apa itu Knowledge Base?

Knowledge Base adalah kumpulan dokumen, link, dan konten yang digunakan AI sebagai referensi untuk menjawab pertanyaan. Setiap course memiliki Knowledge Base terpisah.

### Cara Mengakses

1. Buka **mata kuliah** yang ingin diatur
2. Di menu navigasi course, klik **"Knowledge Base"**
3. Atau: Course -> More -> Knowledge Base

![Knowledge Base Page](pix/guide/daliwidget-kb.png)

### Tab: Documents

Upload dokumen untuk dijadikan referensi AI.

**Format yang didukung:**
| Format | Ekstensi | Keterangan |
|--------|----------|------------|
| PDF | `.pdf` | Dokumen PDF teks atau gambar |
| Word | `.docx` | Dokumen Microsoft Word |
| Text | `.txt` | File teks biasa |
| PowerPoint | `.pptx` | Presentasi PowerPoint |

**Cara upload:**
1. Klik tab **"Documents"**
2. Klik tombol **"Upload"** atau drag-and-drop file
3. Tunggu hingga upload selesai
4. File akan diproses di background (jika mode Async)
5. Status berubah dari **"Queued"** -> **"Processing"** -> **"Done"**

![Upload Document](pix/guide/daliwidget-upload.png)

**Tips:** Untuk PDF besar (> 10 MB), pastikan admin mengatur Sync Mode ke Async agar tidak memblokir halaman.

### Tab: Web Links

Tambahkan URL web untuk di-scrape sebagai referensi AI.

**Cara menambahkan:**
1. Klik tab **"Web Links"**
2. Masukkan **URL** di field yang tersedia
3. Berikan **nama** untuk link (opsional)
4. Klik **"Add URL"**
5. Konten halaman akan di-scrape dan diproses

**Tips:** Pastikan URL bisa diakses dari server Moodle. Halaman yang memerlukan login atau JavaScript mungkin tidak bisa di-scrape dengan baik.

### Tab: YouTube Videos

Tambahkan video YouTube untuk diambil transkripnya sebagai referensi AI.

**Cara menambahkan:**
1. Klik tab **"YouTube Videos"**
2. Masukkan **URL YouTube** (contoh: `https://www.youtube.com/watch?v=xxxxx`)
3. Klik **"Add Video"**
4. Transkrip video akan diekstrak dan diproses

**Tips:** Video dengan subtitle/CC otomatis akan menghasilkan transkrip yang lebih baik.

### Tab: Activity Sync

Otomatis konten dari aktivitas kursus Moodle.

**Aktivitas yang didukung:**
| Aktivitas | Konten yang di-sync |
|-----------|---------------------|
| **Page** | Judul + konten halaman |
| **Book** | Judul + semua chapter |
| **Assignment** | Judul + deskripsi + instruksi |
| **Quiz** | Judul + deskripsi + pertanyaan |
| **SCORM** | Judul + deskripsi |
| **Forum** | Judul + deskripsi |
| **Lesson** | Judul + deskripsi |
| **Folder** | Judul + deskripsi |
| **Label** | Konten label |
| **Resource** | Judul + deskripsi |
| **URL** | Judul + URL + deskripsi |

**Cara sync:**
1. Klik tab **"Activity Sync"**
2. Daftar aktivitas kursus akan muncul
3. Klik **"Sync"** pada aktivitas tertentu, atau
4. Klik **"Sync All Activities"** untuk sync semua sekaligus

![Activity Sync](pix/guide/daliwidget-activity-sync.png)

**Tips:** Lakukan sync setelah mengubah konten aktivitas agar AI memiliki referensi terbaru.

---

## 1.3 Menggunakan Widget Chat

### Cara Pakai

Widget chat muncul sebagai **floating button** di pojok kanan bawah halaman Moodle.

1. Klik **ikon chat** di pojok kanan bawah
2. Ketik pertanyaan di kolom input
3. Tekan **Enter** atau klik tombol **Kirim**
4. AI akan merespons berdasarkan konteks halaman dan Knowledge Base

![Widget Chat](pix/guide/daliwidget-chat.png)

### Fitur Widget

| Fitur | Keterangan |
|-------|-----------|
| **Context-aware** | AI tahu halaman mana Anda sedang buka, course apa, activity apa |
| **Course-scoped** | AI menggunakan Knowledge Base course sebagai referensi |
| **Strict mode** | Jika admin mengaktifkan, AI hanya jawab dari materi tersinkron |
| **Multi-turn** | Bisa melanjutkan percakapan (follow-up questions) |

### Kapan Widget Muncul

| Halaman | Widget Muncul? |
|---------|---------------|
| Dashboard | [v] |
| Course page | [v] |
| Activity page (Page, Book, dll) | [v] |
| Quiz page | [x] (tidak muncul) |
| Admin pages | [x] (tidak muncul) |

**Catatan:** Widget sengaja tidak muncul di halaman quiz untuk mencegah kecurangan.

### Contoh Penggunaan

| Pertanyaan | Konteks |
|-----------|---------|
| "Jelaskan materi bab 3" | Saat membuka halaman course |
| "Buatkan 5 soal tentang topik ini" | Saat membuka Page/Book |
| "Apa tugas yang harus dikerjakan?" | Saat membuka Assignment |
| "Ringkaskan diskusi forum ini" | Saat membuka Forum |

---

## 1.4 Troubleshooting

### Widget Tidak Muncul

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| Widget dinonaktifkan admin | Hubungi admin untuk mengaktifkan |
| Halaman quiz | Widget memang tidak muncul di quiz |
| JavaScript error | Buka F12 -> Console, cek error |

### AI Tidak Merespons

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| API Key belum dikonfigurasi | Hubungi admin |
| Dali API down | Hubungi admin, cek server |
| Network issue | Coba refresh halaman |

### Knowledge Base Tidak Ter-sync

| Kemungkinan Penyebab | Solusi |
|---------------------|--------|
| File terlalu besar | Kompres file atau minta admin naikkan limit |
| Sync belum selesai | Tunggu beberapa menit, refresh halaman |
| Mode Async | Status bisa dicek di halaman Knowledge Base |

---

# Bagian 2: AI Quiz Generator — Generate Soal

## 2.1 Apa itu AI Quiz Generator?

AI Quiz Generator memungkinkan Anda **menggenerate soal quiz secara otomatis** menggunakan AI. Fitur ini mendukung:

- **Generate dari topic** — ketik topik, AI buatkan soal
- **Generate dari PDF** — upload PDF, AI baca dan buatkan soal
- **Generate dari Knowledge Source** — gunakan materi yang sudah di-sync ke Dali Knowledge Base
- **4 tipe soal** — Multiple Choice, True/False, Short Answer, Essay
- **3 tingkat kesulitan** — Easy, Medium, Hard
- **10 bahasa** — termasuk Indonesia, Inggris, Thai, Vietnam, dll
- **Langsung simpan ke Question Bank** — tidak perlu copy-paste

### Alur Kerja

```
+-------------------------------------------------+
|  1. Pilih Sumber                                |
|     - Topic (ketik manual)                      |
|     - PDF (upload file)                         |
|     - Knowledge Source (dari Dali KB)           |
+------------------+------------------------------+
                   |
       +-----------v-----------+
       |  2. Atur Parameter    |
       |     - Jumlah soal     |
       |     - Tipe soal       |
       |     - Kesulitan       |
       |     - Bahasa          |
       |     - Kategori        |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  3. Generate          |
       |     - AI memproses    |
       |     - Preview soal    |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  4. Save ke Bank      |
       |     - Pilih soal      |
       |     - Simpan ke QB    |
       +-----------------------+
```

---

## 2.2 Generate Soal dari Topic

### Langkah-Langkah

1. Buka **mata kuliah** yang ingin ditambahkan soal
2. Buka **Question Bank** -> **Questions** -> **"Create a new question..."** atau langsung ke halaman generate
3. Klik **"Generate Questions with AI"** (jika tersedia di menu)
4. Di form generate:
   - **Source**: Pilih **"Upload new PDF file"** (default)
   - **Topic/Subject**: Ketik topik soal (contoh: "Photosynthesis in plants")
   - **Number of Questions**: Pilih jumlah soal (contoh: 5)
   - **Question Type**: Pilih tipe (contoh: Multiple Choice)
   - **Difficulty Level**: Pilih kesulitan (contoh: Medium)
   - **Language**: Pilih bahasa (contoh: Indonesian)
   - **Question Category**: Pilih kategori tujuan
5. Klik **"Generate Questions"**
6. Tunggu hingga AI selesai memproses
7. Preview soal yang dihasilkan
8. Klik **"Save to Question Bank"** untuk menyimpan

![Generate from Topic](pix/guide/aiquizgen-topic.png)

**Tips:** Semakin spesifik topik, semakin bagus soal yang dihasilkan. Contoh buruk: "Matematika". Contoh bagus: "Operasi hitung pecahan untuk kelas 5 SD".

---

## 2.3 Generate Soal dari PDF

### Langkah-Langkah

1. Buka form generate soal
2. Di field **Source**, pilih **"Upload new PDF file"**
3. Klik **"Upload PDF Document"** -> pilih file PDF dari komputer Anda
4. Isi field lainnya (Number of Questions, Question Type, dll)
5. Klik **"Generate Questions"**
6. AI akan membaca PDF dan membuat soal berdasarkan isinya

![Generate from PDF](pix/guide/aiquizgen-pdf.png)

**Catatan:**
- Ukuran PDF maksimum: **10 MB**
- Format: **PDF saja** (.pdf)
- PDF harus berisi **teks** (bukan scan gambar tanpa OCR)
- AI akan mengekstrak teks dari PDF terlebih dahulu, lalu generate soal

**Tips:** PDF dengan struktur yang jelas (heading, subheading, paragraf) menghasilkan soal yang lebih baik daripada PDF dengan format bebas.

---

## 2.4 Generate Soal dari Knowledge Source

### Apa itu Knowledge Source?

Knowledge Source adalah materi yang sudah di-sync ke Dali Knowledge Base melalui plugin Dali AI Widget. Jika Anda sudah mengupload dokumen atau sync activity di Knowledge Base, Anda bisa menggunakan materi tersebut sebagai sumber soal.

### Langkah-Langkah

1. Pastikan **Knowledge Base** course sudah terisi (lihat Bagian 1.2)
2. Buka form generate soal
3. Di field **Source**, pilih **"Select from course materials"**
4. Field **"Select Source"** akan muncul dengan daftar knowledge sources yang sudah ready
5. Pilih source yang ingin digunakan
6. Isi field lainnya (Number of Questions, Question Type, dll)
7. Klik **"Generate Questions"**

![Generate from Knowledge Source](pix/guide/aiquizgen-source.png)

**Tips:** Jika daftar source kosong, sync materi terlebih dahulu di halaman Knowledge Base (lihat Bagian 1.2).

---

## 2.5 Penjelasan Lengkap Setiap Field Form

Berikut penjelasan detail setiap field di form generate soal:

---

### [edit] Topic/Subject

| | |
|---|---|
| **Tipe** | Textarea (4 baris × 60 kolom) |
| **Default** | Kosong |
| **Fungsi** | Topik atau subjek untuk soal yang akan di-generate |

**Penjelasan:**
Masukkan topik spesifik untuk soal. Semakin detail, semakin bagus hasilnya. Bisa dikombinasikan dengan PDF atau Knowledge Source untuk konteks tambahan.

**Contoh yang bagus:**
- "Photosynthesis in plants — light-dependent reactions and Calvin cycle"
- "Dasar-dasar pemrograman Python: variabel, tipe data, dan operator"
- "Sejarah kemerdekaan Indonesia 1945"

**Tips:** Field ini bisa dikosongkan jika Anda sudah memilih PDF atau Knowledge Source sebagai sumber.

---

### [folder] Source

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Upload new PDF file |
| **Pilihan** | Upload new PDF file, Select from course materials |
| **Fungsi** | Menentukan sumber konten untuk generate soal |

**Penjelasan:**
- **Upload new PDF file**: Upload PDF langsung dari komputer
- **Select from course materials**: Pilih dari knowledge sources yang sudah di-sync di Dali Knowledge Base

---

### [doc] Upload PDF Document

| | |
|---|---|
| **Tipe** | File manager |
| **Batas** | 1 file, maks 10 MB, format .pdf |
| **Fungsi** | Upload PDF untuk dijadikan sumber soal |

**Penjelasan:**
Muncul hanya jika Source = "Upload new PDF file". PDF akan di-upload ke Moodle, lalu teksnya diekstrak dan dikirim ke AI.

---

### [books] Select Source

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Fungsi** | Memilih knowledge source dari course |

**Penjelasan:**
Muncul hanya jika Source = "Select from course materials". Daftar berisi knowledge sources yang sudah di-sync dan berstatus "ready" di Dali Knowledge Base.

**Tips:** Jika daftar kosong, pastikan Knowledge Base course sudah terisi.

---

### [#] Number of Questions

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | 5 |
| **Range** | 1 sampai Maximum Questions per Request (default: 20) |
| **Fungsi** | Jumlah soal yang akan di-generate |

**Rekomendasi:**
| Kegunaan | Jumlah |
|----------|--------|
| Quiz harian | 5-10 |
| UTS | 20-30 |
| UAS | 30-50 |

---

### [list] Question Type

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Multiple Choice |
| **Pilihan** | Multiple Choice, True/False, Short Answer, Essay |
| **Fungsi** | Tipe soal yang akan di-generate |

**Penjelasan:**
| Tipe | Format | Cocok untuk |
|------|--------|-------------|
| **Multiple Choice** | 1 pertanyaan + 4 opsi jawaban | Penilaian objektif, banyak materi |
| **True/False** | Pernyataan benar/salah | Konsep dasar, fakta |
| **Short Answer** | Pertanyaan terbuka, jawaban pendek | Definisi, istilah |
| **Essay** | Pertanyaan terbuka, jawaban panjang | Analisis, argumentasi |

---

### [chart] Difficulty Level

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Medium |
| **Pilihan** | Easy, Medium, Hard |
| **Fungsi** | Tingkat kesulitan soal |

**Rekomendasi:**
| Level | Cocok untuk |
|-------|-------------|
| **Easy** | Quiz latihan, materi dasar |
| **Medium** | UTS, evaluasi reguler |
| **Hard** | UAS, tantangan, olimpiade |

---

### [web] Language

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Indonesian (Bahasa Indonesia) |
| **Pilihan** | English, Indonesian, Thai, Vietnamese, Malay, Filipino, Burmese, Khmer, Lao, Tetum |
| **Fungsi** | Bahasa soal dan jawaban |

---

### [file] Question Category

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Kategori pertama yang tersedia |
| **Fungsi** | Kategori di Question Bank tempat soal akan disimpan |

**Penjelasan:**
Soal yang di-generate akan disimpan ke kategori yang dipilih. Pilih kategori yang sesuai dengan mata kuliah dan topik.

**Tips:** Jika kategori belum ada, buat dulu di Question Bank -> Categories.

---

### [msg] Additional Instructions

| | |
|---|---|
| **Tipe** | Textarea (3 baris × 60 kolom) |
| **Default** | Kosong |
| **Fungsi** | Instruksi tambahan untuk AI |

**Penjelasan:**
Field opsional untuk memberikan instruksi khusus ke AI. Berguna untuk:
- Membatasi cakupan soal: "Hanya tentang bab 3"
- Meminta format tertentu: "Sertakan penjelasan di setiap jawaban"
- Menambahkan konteks: "Untuk mahasiswa semester 1"

---

## 2.6 Preview dan Save ke Question Bank

### Preview Soal

Setelah AI selesai generate, Anda akan melihat **preview** berisi:

| Kolom | Isi |
|-------|-----|
| **Question** | Teks pertanyaan |
| **Answers** | Opsi jawaban (untuk MCQ/True-False) |
| **Correct Answer** | Jawaban yang benar |
| **Feedback** | Penjelasan jawaban |

![Preview Questions](pix/guide/aiquizgen-preview.png)

### Save ke Question Bank

1. Review semua soal di preview
2. Centang soal yang ingin disimpan (atau centang semua)
3. Klik **"Save to Question Bank"**
4. Soal akan masuk ke kategori yang dipilih
5. Pesan sukses: "X questions saved to question bank successfully!"

### Regenerate

Jika soal kurang memuaskan:
1. Klik **"Regenerate"**
2. AI akan membuat soal baru dengan parameter yang sama
3. Review dan save lagi

**Tips:** Jika hasil regenerate juga kurang bagus, coba perjelas topik atau tambahkan instruksi di field "Additional Instructions".

---

## 2.7 Troubleshooting

### API Key Tidak Dikonfigurasi

| Error | "API key is not configured" |
|-------|---------------------------|
| **Solusi** | Hubungi admin untuk mengkonfigurasi API Key |

### Koneksi Gagal

| Error | "Connection failed" |
|-------|-------------------|
| **Solusi** | Hubungi admin. Kemungkinan Base URL salah atau Dali API down. |

### PDF Gagal Diproses

| Error | "Error processing PDF" |
|-------|---------------------|
| **Solusi** | 1. Cek ukuran PDF (< 10 MB). 2. Buka PDF — pastikan bisa di-select teksnya. 3. Coba PDF lain. |

### Soal Tidak Muncul di Question Bank

| Masalah | Soal sudah di-save tapi tidak muncul |
|---------|-------------------------------------|
| **Solusi** | 1. Cek kategori yang dipilih saat save. 2. Di Question Bank, hapus filter -> tampilkan semua. |

---

# Bagian 3: AI Grading — Koreksi Essay

## 3.1 Apa itu AI Grading?

AI Grading memungkinkan Anda **mengoreksi essay secara otomatis** menggunakan AI. Fitur ini mendukung:

- **Suggest Grade** — AI memberikan nilai + feedback untuk 1 essay
- **Bulk AI Grade All** — AI koreksi semua essay yang terlihat di halaman grading
- **Auto AI Grade** — AI koreksi semua essay yang belum dinilai untuk 1 soal
- **Auto Grade ALL Questions** — AI koreksi semua essay yang belum dinilai di semua soal
- **Confidence Level** — AI menunjukkan seberapa yakin dengan nilai yang diberikan

### Alur Kerja

```
+-------------------------------------------------+
|  1. Essay Masuk                                 |
|     - Peserta submit essay                      |
|     - Essay menunggu untuk dikoreksi            |
+------------------+------------------------------+
                   |
       +-----------v-----------+
       |  2. AI Menganalisis   |
|     |     - Baca pertanyaan  |
|     |     - Baca rubrik      |
|     |     - Baca jawaban     |
|     |     - Bandingkan       |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  3. AI Memberikan     |
       |     - Suggested grade |
       |     - Feedback        |
       |     - Explanation     |
       |     - Confidence      |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  4. Review & Apply    |
|     |     - Trainer review   |
|     |     - Apply/override   |
       +-----------------------+
```

---

## 3.2 Suggest Grade (Satu per Satu)

### Apa itu Suggest Grade?

Fitur untuk mendapatkan **saran nilai dari AI** untuk 1 essay. Anda bisa review, edit, dan apply.

### Langkah-Langkah

1. Buka **quiz/assignment** yang memiliki essay
2. Klik **"Manual grading"** atau buka grading page
3. Di sebelah setiap jawaban essay, klik tombol **"AI Suggest Grade"**
4. AI akan menganalisis jawaban dan memberikan:
   - **Suggested Grade** — nilai yang disarankan
   - **Feedback for Student** — feedback untuk peserta
   - **Explanation for Teacher** — penjelasan untuk trainer
5. Review saran dari AI
6. Klik **"Apply Grade"** untuk menerapkan, atau
7. Edit nilai/feedback manual, lalu klik **"Apply Grade"**

![AI Suggest Grade](pix/guide/aigrading-suggest.png)

**Tips:** Selalu review saran AI sebelum apply. AI bisa salah, terutama untuk soal yang memerlukan penilaian subjektif.

---

## 3.3 Bulk AI Grade All

### Apa itu Bulk AI Grade All?

Fitur untuk **mengoreksi semua essay yang terlihat** di halaman grading sekaligus.

### Langkah-Langkah

1. Buka **grading page** (Manual grading)
2. Pastikan semua essay yang ingin dikoreksi **terlihat di halaman**
3. Klik tombol **"Bulk AI Grade All"**
4. Konfirmasi dialog: "Are you sure you want to auto-grade all ungraded essays?"
5. AI akan memproses satu per satu dengan progress bar
6. Setelah selesai, review hasilnya
7. Klik **"Apply All Grades"** untuk menerapkan semua, atau
8. Review satu per satu dan apply manual

![Bulk AI Grade](pix/guide/aigrading-bulk.png)

**Catatan:** Hanya essay yang **belum dinilai** yang akan di-grading. Essay yang sudah dinilai tidak akan diubah.

---

## 3.4 Auto AI Grade

### Apa itu Auto AI Grade?

Fitur untuk **otomatis mengoreksi semua essay yang belum dinilai** untuk 1 soal tertentu. Tidak perlu review — langsung apply.

### Langkah-Langkah

1. Buka **grading page** untuk 1 soal essay
2. Klik tombol **"Auto AI Grade"**
3. Konfirmasi dialog
4. AI akan memproses semua essay yang belum dinilai
5. Nilai langsung di-apply tanpa review
6. Ringkasan: "Auto-grading complete: X graded, Y failed."

**[!] Peringatan:** Fitur ini **langsung apply** tanpa review. Gunakan hanya jika Anda yakin dengan rubrik dan soal yang digunakan.

---

## 3.5 Auto Grade ALL Questions

### Apa itu Auto Grade ALL Questions?

Fitur untuk **otomatis mengoreksi semua essay yang belum dinilai** di **semua soal** dalam satu quiz/assignment.

### Langkah-Langkah

1. Buka **grading overview** quiz/assignment
2. Klik tombol **"Auto Grade ALL Questions"**
3. Konfirmasi dialog
4. AI akan memproses semua essay di semua soal
5. Ringkasan: "Auto-grading complete: X graded, Y failed."

**[!] Peringatan:** Sama seperti Auto AI Grade, fitur ini **langsung apply** tanpa review. Gunakan dengan hati-hati.

---

## 3.6 Memahami Confidence Level

### Apa itu Confidence Level?

Confidence Level menunjukkan **seberapa yakin AI** dengan nilai yang diberikan. Skala 0-100%.

### Interpretasi

| Confidence | Arti | Rekomendasi |
|------------|------|-------------|
| **80-100%** | AI sangat yakin | Bisa langsung apply (tetap review) |
| **60-79%** | AI cukup yakin | Review disarankan |
| **40-59%** | AI kurang yakin | Review wajib |
| **0-39%** | AI tidak yakin | Review wajib, pertimbangkan manual grading |

### Faktor yang Mempengaruhi Confidence

| Faktor | Confidence Naik | Confidence Turun |
|--------|-----------------|------------------|
| **"Information for graders" terisi** | [v] | [x] |
| **Rubrik spesifik** | [v] | [x] |
| **Jawaban relevan** | [v] | [x] |
| **Jawaban tidak relevan** | [x] | [v] |
| **Jawaban ambigu** | [x] | [v] |
| **Soal subjektif** | [x] | [v] |

**Tips:** Untuk confidence rendah, selalu review manual. AI lebih akurat untuk soal fakta dibanding soal opini.

---

## 3.7 Tips Mendapatkan Hasil Terbaik

### Isi "Information for graders"!

Ini **tips paling penting** untuk mendapatkan grading AI yang akurat. Cara mengisinya:

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

## 3.8 Troubleshooting

### API Key Tidak Dikonfigurasi

| Error | "Dali API key is not configured" |
|-------|--------------------------------|
| **Solusi** | Hubungi admin untuk mengkonfigurasi API Key |

### Koneksi Gagal

| Error | "Dali API error" |
|-------|-----------------|
| **Solusi** | Hubungi admin. Kemungkinan Base URL salah atau Dali API down. |

### Nilai Tidak Akurat

| Masalah | AI memberikan nilai yang tidak sesuai |
|---------|-------------------------------------|
| **Solusi** | 1. Isi "Information for graders" di soal. 2. Minta admin kustomisasi rubrik. 3. Review manual sebelum apply. |

### Essay Tidak Ter-deteksi

| Masalah | Tombol AI grading tidak muncul |
|---------|------------------------------|
| **Solusi** | 1. Pastikan soal bertipe Essay. 2. Pastikan ada jawaban yang belum dinilai. 3. Hubungi admin jika masih tidak muncul. |

---

# Bagian 4: AI Lesson Plan — Generate Rencana Pembelajaran

## 4.1 Apa itu AI Lesson Plan?

AI Lesson Plan memungkinkan Anda **generate rencana pembelajaran (lesson plan)** secara otomatis menggunakan AI. Fitur ini mendukung:

- **Generate RPS/Syllabus** — rencana pembelajaran semester
- **Konteks kursus** — AI menggunakan metadata, sections, dan activities kursus
- **Knowledge source** — gunakan materi dari Dali Knowledge Base
- **Publish ke Moodle** — langsung buat sections dan activities di kursus
- **Save draft** — simpan lesson plan untuk diedit/dipublish nanti
- **Download JSON** — export lesson plan dalam format JSON

### Alur Kerja

```
+-------------------------------------------------+
|  1. Isi Form                                    |
|     - Topic, Level, Duration, Meetings          |
|     - Language, Density, Curriculum             |
|     - Course context checkboxes                 |
|     - Knowledge source (opsional)               |
+------------------+------------------------------+
                   |
       +-----------v-----------+
       |  2. AI Generate       |
|     |     - Proses konteks   |
|     |     - Generate plan    |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  3. Preview & Edit    |
|     |     - Review plan      |
|     |     - Edit sections    |
|     |     - Edit activities  |
       +-----------+-----------+
                   |
       +-----------v-----------+
       |  4. Publish / Export  |
|     |     - Publish ke course|
|     |     - Download JSON    |
|     |     - Save draft       |
       +-----------------------+
```

---

## 4.2 Generate Lesson Plan

### Langkah-Langkah

1. Buka **mata kuliah** yang ingin dibuatkan lesson plan
2. Di menu navigasi course, klik **"AI Lesson Plan"** atau **"Generate Lesson Plan"**
3. Isi form generate (lihat bagian 4.3 untuk detail setiap field)
4. Klik **"Generate Lesson Plan"**
5. Tunggu hingga AI selesai memproses
6. Preview lesson plan yang dihasilkan
7. Edit jika perlu
8. Publish atau download

![Generate Lesson Plan](pix/guide/ailessonplan-generate.png)

**Tips:** Semakin lengkap informasi yang Anda berikan (topic, level, curriculum, context), semakin bagus lesson plan yang dihasilkan.

---

## 4.3 Penjelasan Lengkap Setiap Field Form

---

### [edit] Topic / subject focus

| | |
|---|---|
| **Tipe** | Textarea (4 baris × 70 kolom) |
| **Default** | Kosong |
| **Fungsi** | Topik utama rencana pembelajaran |

**Penjelasan:**
Masukkan topik utama yang ingin dibuatkan lesson plan. Bisa spesifik atau umum. Jika Anda ingin AI mengikuti konteks kursus, cukup isi topik yang luas.

**Contoh:**
- "Dasar Pemrograman Python"
- "Project-based learning for web development"
- "LMS onboarding for trainers"

---

###  Learner level

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Beginner / mixed ability |
| **Pilihan** | 9 level |

**Daftar Level:**
| Level | Keterangan |
|-------|-----------|
| Beginner / mixed ability | Pemula dengan kemampuan campuran |
| Beginner | Pemula |
| Intermediate | Menengah |
| Advanced | Lanjutan |
| Foundation / basic education | Pendidikan dasar |
| Secondary / pre-university | Menengah atas |
| Vocational / skills training | Pelatihan kejuruan |
| Higher education / university | Perguruan tinggi |
| Professional / corporate training | Pelatihan profesional |

---

### [timer] Duration per meeting

| | |
|---|---|
| **Tipe** | Text (30 karakter) |
| **Default** | `2 x 50 menit` |
| **Fungsi** | Durasi setiap pertemuan |

**Contoh:**
- `2 x 50 menit` — 2 sesi @ 50 menit per pertemuan
- `1 x 90 menit` — 1 sesi @ 90 menit
- `3 x 40 menit` — 3 sesi @ 40 menit

---

### [cal] Number of meetings

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | 4 |
| **Pilihan** | 1, 2, 3, 4, 5 |
| **Fungsi** | Jumlah pertemuan dalam satu minggu/materi |

---

### [web] Language

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Indonesian |
| **Pilihan** | Indonesian, English |
| **Fungsi** | Bahasa lesson plan |

---

### [chart] Activity density

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | Balanced |
| **Pilihan** | Light, Balanced, Rich |
| **Fungsi** | Jumlah aktivitas per section |

**Penjelasan:**
| Density | Aktivitas/Section | Cocok untuk |
|---------|-------------------|-------------|
| **Light** | 1-2 aktivitas | Pertemuan singkat, review |
| **Balanced** | 2-3 aktivitas | Pembelajaran reguler |
| **Rich** | 3-5 aktivitas | Workshop, bootcamp |

---

### [books] Curriculum reference / standard

| | |
|---|---|
| **Tipe** | Textarea (4 baris × 70 kolom) |
| **Default** | Kosong |
| **Fungsi** | Referensi kurikulum atau standar kompetensi |

**Penjelasan:**
Masukkan referensi kurikulum yang ingin diikuti. Contoh:
- "KKNI Level 5 - Teknik Informatika"
- "CP: CP-1, CP-2, CP-3 (Capaian Pembelajaran)"
- "SKKNI: Programmer Junior"

**Tips:** Field ini opsional, tapi sangat membantu AI membuat lesson plan yang sesuai standar.

---

###  Course context checkboxes

| | |
|---|---|
| **Tipe** | Checkbox × 3 |
| **Default** | Semua dicentang (kecuali knowledge source) |

**Checkbox yang tersedia:**

| Checkbox | Default | Fungsi |
|----------|---------|--------|
| **Course metadata and summary** | [v] | Sertakan nama, deskripsi, dan metadata kursus |
| **Course sections/topics** | [v] | Sertakan daftar sections kursus |
| **Activity list and short descriptions** | [v] | Sertakan daftar aktivitas dan deskripsi singkat |
| **Use synced Dali knowledge source** | [x] | Gunakan materi dari Dali Knowledge Base |

**Tips:** Centang semua checkbox untuk konteks terlengkap. Uncheck jika Anda ingin lesson plan yang lebih "fresh" tanpa terpengaruh struktur kursus yang ada.

---

### [folder] Knowledge source

| | |
|---|---|
| **Tipe** | Dropdown select |
| **Default** | "Do not use a knowledge source" |
| **Fungsi** | Memilih knowledge source dari Dali KB |

**Penjelasan:**
Field ini aktif hanya jika checkbox **"Use synced Dali knowledge source"** dicentang. Daftar berisi knowledge sources yang sudah di-sync dan berstatus "ready" di Dali Knowledge Base.

**Tips:** Jika daftar kosong, sync materi terlebih dahulu di halaman Knowledge Base (lihat Bagian 1.2).

---

### [msg] Additional instructions

| | |
|---|---|
| **Tipe** | Textarea (4 baris × 70 kolom) |
| **Default** | Kosong |
| **Fungsi** | Instruksi tambahan untuk AI |

**Contoh:**
- "Fokus pada praktikum, minimal teori"
- "Gunakan metode Problem-Based Learning"
- "Sertakan assessment di setiap pertemuan"

---

## 4.4 Preview dan Edit Plan

### Apa yang Ditampilkan?

Setelah AI selesai generate, Anda akan melihat preview berisi:

| Bagian | Isi |
|--------|-----|
| **Plan title** | Judul lesson plan |
| **Description** | Deskripsi umum |
| **Course summary** | Ringkasan kursus |
| **Learning outcomes** | Capaian pembelajaran |
| **Meeting plan** | Rencana per pertemuan (weekly) |
| **Assessment plan** | Rencana penilaian |
| **References** | Referensi |

### Edit Plan

Anda bisa mengedit:
- **Section titles** — judul setiap section/minggu
- **Section summaries** — ringkasan setiap section
- **Objectives** — tujuan pembelajaran
- **Activity types** — tipe aktivitas (Page, Forum, Assignment, dll)
- **Activity titles** — judul aktivitas
- **Activity content** — instruksi/konten aktivitas

**Tips:** Edit sebelum publish. Setelah publish, Anda masih bisa edit langsung di Moodle.

---

## 4.5 Publish ke Moodle Course

### Apa itu Publish?

Publish adalah fitur untuk **membuat sections dan activities** di Moodle course berdasarkan lesson plan yang sudah di-generate.

### Placement Options

| Opsi | Keterangan |
|------|-----------|
| **Append as new sections** | Menambahkan sections baru setelah sections yang sudah ada |
| **Update existing sections** | Meng-update sections yang sudah ada mulai dari section 1 |
| **Start from specific section** | Mulai dari section nomor tertentu |

### Langkah-Langkah Publish

1. Setelah preview lesson plan, klik **"Publish to Moodle"**
2. Pilih **placement option** (Append/Update/Custom)
3. Jika Custom, masukkan **nomor section** awal
4. Review **publish preview** — tampilkan sections dan activities yang akan dibuat
5. Edit jika perlu (uncheck item yang tidak ingin dipublish)
6. Klik **"Confirm publish"**
7. Tunggu hingga proses selesai
8. Pesan sukses: "Course skeleton published to Moodle."

![Publish Preview](pix/guide/ailessonplan-publish.png)

### Activity Types yang Didukung

| Tipe | Status | Keterangan |
|------|--------|------------|
| **Page** | [v] Full support | Membuat halaman Page dengan konten |
| **Forum** | [v] Full support | Membuat forum diskusi |
| **Assignment** | [v] Full support | Membuat tugas |
| **Quiz** | [!] Placeholder | Membuat container quiz, soal ditambah manual/AI Quiz Gen |
| **Label** | [v] Full support | Membuat label teks |
| **URL** | [v] Full support | Membuat link URL |
| **SCORM** | [!] Placeholder | Membuat placeholder, upload SCORM manual |
| **Book** | [!] Placeholder | Membuat placeholder, isi konten manual |
| **Choice** | [!] Placeholder | Membuat placeholder |
| **Feedback** | [!] Placeholder | Membuat placeholder |
| **Glossary** | [!] Placeholder | Membuat placeholder |
| **Wiki** | [!] Placeholder | Membuat placeholder |

**Tips:** Untuk tipe yang "Placeholder", Anda perlu melengkapi konten secara manual setelah publish.

---

## 4.6 Download JSON

### Apa itu Download JSON?

Fitur untuk **mengekspor lesson plan** dalam format JSON. Berguna untuk:
- Backup lesson plan
- Berbagi dengan colleague
- Import ke sistem lain
- Analisis data

### Langkah-Langkah

1. Setelah preview lesson plan, klik **"Download JSON"**
2. File JSON akan di-download ke komputer Anda
3. File berisi semua data lesson plan dalam format terstruktur

**Tips:** JSON bisa di-upload ulang untuk membuat lesson plan serupa di course lain.

---

## 4.7 Troubleshooting

### API Key Tidak Dikonfigurasi

| Error | "Dali API key is not configured" |
|-------|--------------------------------|
| **Solusi** | Hubungi admin untuk mengkonfigurasi API Key |

### Generate Gagal

| Error | "AI service error" |
|-------|-------------------|
| **Solusi** | Hubungi admin. Kemungkinan Base URL salah atau Dali API down. |

### Publish Gagal

| Masalah | Publish error atau sections tidak muncul |
|---------|----------------------------------------|
| **Solusi** | 1. Pastikan Anda punya hak edit course. 2. Cek placement option. 3. Hubungi admin jika masih gagal. |

### Lesson Plan Tidak Tersimpan

| Masalah | Lesson plan hilang setelah refresh |
|---------|----------------------------------|
| **Solusi** | Hubungi admin untuk mengaktifkan "Enable saved draft history". |

### Knowledge Source Tidak Muncul

| Masalah | Daftar knowledge source kosong |
|---------|------------------------------|
| **Solusi** | 1. Sync materi di Knowledge Base (Bagian 1.2). 2. Centang "Use synced knowledge source". 3. Jika masih kosong, hubungi admin. |

---

# FAQ Trainer

### Q: Apakah saya perlu mengkonfigurasi API Key?

**A:** Tidak. API Key dan Base URL dikonfigurasi oleh admin. Anda hanya perlu menggunakan fitur yang sudah tersedia.

### Q: Bolehkah menggunakan API Key yang sama untuk semua plugin?

**A:** Ya. Semua plugin terhubung ke Dali AI yang sama. Admin biasanya mengkonfigurasi 1 API Key untuk semua.

### Q: Apakah AI bisa bahasa Indonesia?

**A:** Ya. Semua plugin mendukung bahasa Indonesia. AI Quiz Generator mendukung 10 bahasa. AI Lesson Plan mendukung Indonesia dan Inggris. AI Grading otomatis mendeteksi bahasa jawaban.

### Q: Berapa biaya menggunakan AI?

**A:** Tergantung provider AI yang dikonfigurasi di Dali backend. Plugin Moodle tidak memungut biaya — biaya tergantung penggunaan API AI.

### Q: Bagaimana jika Dali API down?

**A:** Semua fitur AI akan tidak tersedia. Moodle tetap berjalan normal. Widget chat akan menampilkan error. Soal/grading yang sudah di-generate tetap tersimpan.

### Q: Apakah bisa offline?

**A:** Tidak. Semua plugin memerlukan koneksi ke Dali AI backend yang memerlukan internet (untuk mengakses AI).

### Q: Bagaimana cara mendapatkan hasil grading yang lebih akurat?

**A:** Isi **"Information for graders"** di setiap soal essay. Ini adalah cara paling efektif untuk meningkatkan akurasi AI grading.

### Q: Apakah saya bisa mengedit lesson plan setelah publish?

**A:** Ya. Setelah publish, Anda bisa edit langsung di Moodle (sections, activities, konten).

### Q: Plugin mana yang harus saya gunakan duluan?

**A:** Mulai dengan **Dali AI Widget** untuk mengisi Knowledge Base course. Kemudian gunakan plugin lain (aiquizgen, aigrading, ailessonplan) sesuai kebutuhan.

### Q: Apakah bisa digunakan di Moodle 4.x dan 5.x?

**A:** Ya. Plugin dikembangkan untuk kompatibel dengan Moodle 4.1+ dan Moodle 5.x.

---

*Dokumentasi ini dibuat untuk Dali AI Plugins v1.0.0. Untuk panduan konfigurasi admin, lihat `GUIDE_ADMIN_DALI_PLUGINS.md`.*
