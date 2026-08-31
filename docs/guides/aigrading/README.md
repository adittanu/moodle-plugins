# AI Grading

Bantuan AI untuk menilai jawaban essay Quiz dan submission Assignment. Saran harus direview pengajar.

| Metadata | Nilai |
|---|---|
| Component | `local_aigrading` |
| Versi | `1.4.0` (`2026072802`) |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi dan konfigurasi

Salin ke `<Moodle dirroot>/local/aigrading`, jalankan upgrade, lalu purge cache. Isi API key, DALI Base URL, rubric default, system prompt, batch size, max retries, dan opsi background task. Gunakan Test Connection setelah credential berubah.

## Mode penggunaan

- Suggest satu jawaban atau file: hanya menghasilkan draft.
- Bulk suggest: menghasilkan beberapa draft untuk review.
- Review by student: preview semua essay `needsgrading` pada attempt, edit nilai/feedback, lalu apply.
- Review by question: preview jawaban untuk satu soal, lalu apply hasil yang disetujui.
- Auto-grade Quiz/Assignment: write-action yang menerapkan nilai; gunakan hanya setelah pengujian rubric.

Akses membutuhkan `local/aigrading:useaigrading`; apply Quiz juga membutuhkan `mod/quiz:grade`, Assignment membutuhkan `mod/assign:grade`.

## Kontrak review

Nilai yang diterapkan harus berada antara 0 dan maximum mark serta question attempt masih berstatus `needsgrading`. Feedback disimpan sebagai plain text. Bila attempt berubah setelah preview, apply dapat ditolak sebagai stale.

## Output DALI

| Field | Penggunaan |
|---|---|
| Grade | Draft nilai numerik. |
| Feedback | Teks untuk peserta setelah review. |
| Explanation | Alasan bagi pengajar. |
| Confidence | Informasi pendukung, bukan jaminan. |

## Arsitektur singkat

Hook dan AMD module menyisipkan kontrol pada halaman grading. External functions memvalidasi context/capability, `dali_service` meminta saran, lalu Moodle Question Engine atau Assignment API menerapkan hasil. Cache plugin menyimpan data pendukung request.

## Keamanan dan data

Jawaban, question text, file submission, rubric, dan instruksi dikirim ke backend DALI. Pastikan kebijakan institusi mengizinkan pemrosesan tersebut. Jangan menerapkan nilai massal tanpa sampling dan jalur koreksi.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Tombol tidak muncul | API key, capability, tipe halaman, essay `needsgrading`. |
| Nilai ditolak | Range, stale state, maximum mark, attempt ID. |
| File tidak terbaca | Format, ukuran, extractor. |
| Output invalid | Base URL, Test Connection, respons JSON backend. |
| Batch lambat | Batch size dan performa backend. |
| Assignment tidak dapat apply | `mod/assign:grade` dan tipe submission. |

## Batas dokumentasi

Opsi background task tersedia sebagai setting, tetapi jalur efektif harus dinilai dari deployment aktif sebelum dianggap sebagai queue terpisah. AI tidak menggantikan keputusan akademik pengajar.