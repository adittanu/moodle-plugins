# AI Lesson Plan for Moodle

Moodle local plugin untuk generate kerangka course mingguan menggunakan Dali/Laravel/Mastra backend.

## Fitur

- Menu course: **AI Lesson Plan**
- Form input topik, level peserta, durasi, jumlah pertemuan, bahasa, dan kepadatan activity
- Ambil konteks otomatis dari course metadata, sections, dan activities
- Opsional memakai synced Dali Knowledge Base source/RAG dari `local_daliwidget`
- Preview course skeleton di Moodle
- Save draft ke database Moodle
- Publish draft menjadi section mingguan dan activity skeleton di course
- AI memilih activity dari modul Moodle yang didukung, seperti Page, Assignment, Forum, Quiz placeholder, URL, dan SCORM placeholder
- Republish aman: update item AI-managed tanpa menduplikasi activity atau menimpa konten manual di luar marker AI
- Download JSON terstruktur

## Backend endpoint

Plugin memanggil:

```text
POST /api/moodle/lesson-plan
```

Header:

```text
X-API-KEY: <Dali API key>
```

Endpoint ini sudah ditambahkan di Laravel app pada:

```text
app/routes/api.php
app/app/Http/Controllers/MoodleProxyController.php
```

## Instalasi

1. Pastikan folder ada di:

```text
moodle41/local/ailessonplan
```

2. Buka Moodle admin:

```text
Site administration → Notifications
```

3. Selesaikan instalasi plugin.

4. Konfigurasi:

```text
Site administration → Plugins → Local plugins → AI Lesson Plan
```

Isi:

- Dali API Base URL, contoh `http://localhost:8000`
- Dali API Key

## Cara pakai

1. Masuk ke course sebagai editing teacher/manager.
2. Klik **AI Lesson Plan** di course navigation.
3. Klik **Generate Lesson Plan**.
4. Isi form.
5. Pilih konteks course yang ingin dipakai.
6. Opsional pilih Dali Knowledge Source.
7. Generate.
8. Review preview.
9. Save draft atau download JSON.
10. Dari halaman draft, klik **Publish to Moodle** untuk preview section dan activity yang akan dibuat.
11. Uncheck activity yang tidak ingin dipublish.
12. Klik **Confirm publish** untuk membuat/update section mingguan dan activity skeleton di course.
