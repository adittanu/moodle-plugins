# AI Lesson Plan

Menghasilkan rencana pembelajaran, menyimpan draft JSON, dan memublikasikan section/activity ke course.

| Metadata | Nilai |
|---|---|
| Component | `local_ailessonplan` |
| Versi | `0.1.0` (`2026050400`) |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi dan konfigurasi

Salin ke `<Moodle dirroot>/local/ailessonplan`, jalankan upgrade, lalu purge cache. Isi DALI API Base URL, API key, dan pilihan logging. API key dapat memakai konfigurasi plugin; integrasi knowledge membutuhkan `local_daliwidget`.

## Alur pengajar

1. Buka course lalu **AI Lesson Plan**.
2. Isi topik, level peserta, durasi, jumlah pertemuan, bahasa, kepadatan activity, referensi kurikulum, dan instruksi.
3. Pilih apakah metadata, section, activity existing, serta knowledge source dikirim sebagai context.
4. Generate dan tinjau seluruh output.
5. Simpan draft atau unduh JSON.
6. Buka preview publish; pilih placement, target section, dan activity yang akan dibuat.
7. Edit skeleton, lalu konfirmasi publish.

Generate dan save draft tidak mengubah course. Publish adalah write-action; backup course dan review preview lebih dahulu.

## Context yang dapat dikirim

- Nama/deskripsi dan metadata course.
- Section existing.
- Activity existing.
- Satu DALI knowledge source course berstatus `ready`.
- Instruksi tambahan pengguna.

## Publish

Publisher dapat membuat atau memperbarui section dan activity skeleton. Plugin menandai item yang dikelolanya untuk republish; konten manual di luar marker tidak dimaksudkan untuk ditimpa. Tipe activity yang tersedia mengikuti implementasi publisher dan modul yang terpasang.

## Arsitektur singkat

`context_builder` membentuk context course. `mastra_client` memanggil endpoint lesson-plan DALI. `plan_renderer` menampilkan struktur. `publisher` menerapkan pilihan preview ke Moodle. Draft tersimpan sebagai JSON pada tabel plugin.

## Keamanan dan data

Context course dapat memuat materi pengajaran. Jangan menyertakan data pribadi yang tidak diperlukan. Publish memerlukan capability pengelolaan activity; sesskey dan validasi Moodle harus tetap berlaku.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Generate gagal | Base URL, API key, backend, bentuk respons. |
| Knowledge source kosong | `local_daliwidget`, source `ready`, course yang sama. |
| Draft gagal tersimpan | JSON hasil dan skema tabel plugin. |
| Publish ditolak | Capability course dan sesskey. |
| Activity tidak dibuat | Modul target terpasang dan pilihan preview aktif. |
| Duplikasi saat republish | Marker item AI-managed dan target section. |

## Batas dokumentasi

Kualitas pedagogis tetap memerlukan review pengajar. Daftar activity efektif bergantung pada modul yang tersedia di instalasi Moodle.