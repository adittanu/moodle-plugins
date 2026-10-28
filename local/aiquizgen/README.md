# AI Quiz Generator

`local_aiquizgen` v1.2.0 — membuat draft soal dengan DALI, mengimpor soal yang sudah ada, dan menyimpannya ke Question Bank Moodle 4.1+ setelah review.

## Mode

1. **Buat soal baru** — tulis topik atau pilih materi/knowledge source, lalu tentukan jumlah, tipe, tingkat kesulitan, dan bahasa.
2. **Impor soal dari PDF** — unggah PDF yang sudah berisi soal dan kunci jawaban. AI mendeteksi tipe soal dan jumlah soal secara otomatis.
   - Format **As-is**: pertahankan tipe soal asli (PG, benar/salah, isian, essay).
   - Format **Multiple choice**: ubah semua soal menjadi PG dengan 1 jawaban benar dan 4 pengecoh.
   - PDF panjang diproses per bagian pada batas soal, lalu digabung dan dibersihkan dari duplikat.
   - Gunakan centang **Remove this question** untuk membuang artefak PDF (misalnya header atau nomor soal yang ikut terbaca).

## Review dan nilai

- Setiap soal memiliki **Maximum mark** sendiri. Nilai ini disimpan sebagai `defaultmark`.
- Soal essay dapat memiliki **Information for grader** berupa kunci jawaban dan rubrik berbasis persentase (total 100%). Rubrik ini dipakai oleh AI grading.
- Tombol **Apply maximum mark to all essay questions** membantu menyamakan skala nilai essay.
- Setelah review, soal disimpan ke Question Bank dan dapat langsung dipakai di kuis Moodle.

## Instalasi ringkas

Salin direktori ini ke `<Moodle dirroot>/local/aiquizgen`, jalankan upgrade Moodle, lalu isi DALI Base URL dan API key pada pengaturan plugin.

Untuk impor PDF, plugin mencari `smalot/pdfparser` dengan urutan berikut:

1. `vendor/autoload.php` di dalam direktori plugin (tidak perlu mengubah Composer Moodle), atau
2. `vendor/` Moodle (jalankan `composer require smalot/pdfparser` di dirroot), atau
3. perintah `pdftotext` yang tersedia di `PATH` server.

Deployment Moodle 5 di repo ini sudah menyertakan `smalot/pdfparser` di `local/aiquizgen/vendor/` beserta autoloader minimal, karena Moodle 5 belum menyertakannya secara default. Jika server memiliki `pdftotext`, cara itu juga bisa dipakai tanpa dependensi PHP.

## Dokumentasi

- [Panduan Markdown](../../docs/guides/aiquizgen/README.md)
- [Panduan PDF](../../docs/guides/aiquizgen/manual.pdf)
- [Fitur PDF](PDF_FEATURE.md)
- [Riwayat perubahan](CHANGELOG.md)

Lisensi: GNU GPL v3 atau lebih baru.
