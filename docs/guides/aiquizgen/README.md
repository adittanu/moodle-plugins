# AI Quiz Generator

Membuat draft soal dengan DALI, meninjau hasil, lalu menyimpannya ke Question Bank Moodle.

| Metadata | Nilai |
|---|---|
| Component | `local_aiquizgen` |
| Versi | `1.1.0` (`2026041602`) |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi dan konfigurasi

Salin ke `<Moodle dirroot>/local/aiquizgen`, jalankan upgrade, lalu purge cache. Pada pengaturan plugin isi DALI API Base URL, API key, maximum questions per request, dan logging. Akses generate membutuhkan `local/aiquizgen:generate` pada course.

## Sumber pertanyaan

- Topik teks.
- Satu PDF maksimal 10 MB.
- Knowledge source course berstatus `ready` dari `local_daliwidget`.

Mode course mengambil context spesifik source melalui endpoint RAG. Bila topic kosong, plugin membentuk query dari judul source. Context hasil dibatasi 10.000 karakter sebelum generation.

## Form generate

| Input | Perilaku |
|---|---|
| Topic | Fokus materi; dapat melengkapi PDF/source. |
| Source | Upload PDF atau course material. |
| Question count | 1 sampai batas admin; default 5. |
| Question type | Multiple choice, true/false, short answer, essay. |
| Answer option count | 3–10; hanya multiple choice; default 5. |
| Difficulty | Easy, medium, hard. |
| Language | Sepuluh bahasa Asia Tenggara/Inggris; default Indonesian. |
| Category | Context system/category/course yang tersedia. |
| Additional instructions | Batas dan gaya tambahan. |

## Alur pengguna

1. Buka course lalu AI Quiz Generator.
2. Pilih sumber dan isi parameter.
3. Generate draft.
4. Tinjau dan edit question text, answer, correct answer, serta feedback.
5. Pilih category yang benar.
6. Simpan hanya soal yang layak ke Question Bank.

Output AI bukan kunci jawaban tepercaya. Pengajar wajib memeriksa akurasi, bias, tingkat kesulitan, dan category sebelum soal dipakai.

## Arsitektur singkat

`generate_form` membangun input dan daftar source siap. `generate.php` mengambil context, memanggil Mastra client, merender review, lalu menyerahkan payload yang sudah diedit ke `question_generator`. Plugin bergantung pada DALI API; integrasi knowledge course bergantung pada `local_daliwidget`.

## Keamanan dan data

Topic, instruksi, PDF/source context, serta parameter generation dikirim ke backend DALI. Jangan unggah data pribadi atau dokumen tanpa hak pemrosesan. API key harus disimpan pada setting password Moodle.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| API key not configured | Setting plugin dan Base URL. |
| Course material kosong | `local_daliwidget`, source `ready`, scope course. |
| PDF ditolak | Ekstensi PDF, ukuran 10 MB, file tunggal. |
| Retrieval kosong | Source ID/knowledge ID dan relevansi topic. |
| Category tidak tersedia | Question category pada context course/parent/system. |
| Soal tidak tersimpan | Payload review, answer kosong, capability, category. |

## Batas dokumentasi

Model AI, provider, dan format internal backend tidak ditentukan plugin. Dukungan PDF scan tanpa teks bergantung pada backend/parser yang terpasang.