# Dali AI Widget

Asisten chat kontekstual dan pengelolaan knowledge source untuk Moodle.

| Metadata | Nilai |
|---|---|
| Component | `local_daliwidget` |
| Versi | `v1.4.5` (`2026082700`) |
| Moodle minimum | 4.1 (`2022112800`) |

## Pengguna dan fungsi

- Administrator: koneksi DALI, kebijakan knowledge, tampilan, persona, global knowledge, koneksi WordPress.
- Pengajar dengan `moodle/course:update`: knowledge source course dan sinkronisasi activity.
- Pengguna berizin `local/daliwidget:view`: chat pada halaman yang diizinkan.
- Widget tidak ditampilkan pada halaman Quiz. Guest tidak mendapat widget pada mode `site_wide`.

## Instalasi

Salin plugin ke `<Moodle dirroot>/local/daliwidget`, jalankan upgrade Moodle, lalu purge cache. Konfigurasi berada di **Site administration > Plugins > Local plugins > Dali AI Widget**.

## Konfigurasi utama

| Setting | Default | Fungsi |
|---|---|---|
| Enabled | Aktif | Sakelar widget site. |
| API key | Kosong | Credential DALI; widget tidak dirender bila kosong. |
| Base URL | `https://dali-app.test` | Root aplikasi DALI. |
| Max upload | 20 MB | Batas upload knowledge. |
| Signed URL | Nonaktif | Memungkinkan backend mengambil file melalui URL bertanda tangan. |
| Sync mode | `async` | Sinkron atau antrean untuk knowledge sync. |
| Knowledge access mode | `course_scoped` | Membatasi knowledge ke course aktif atau memakai cakupan site. |
| Answer source policy | `knowledge_only` | Hanya knowledge atau mengutamakan knowledge. |
| Appearance | Default aplikasi | Nama, sambutan, tema, warna aksen, radius, avatar. |
| Persona | Default | Gaya bicara dan instruksi tambahan maksimal 2.000 karakter. |

Jangan menaruh API key pada dokumentasi, log publik, atau screenshot.

## Penggunaan administrator

### Global Knowledge Base

Buka menu **Global Knowledge Base**. Tambah source sesuai opsi UI, pantau status, retry kegagalan, atau hapus source yang tidak lagi diperlukan. Status `ready` berarti source dapat dipakai retrieval; `queued` dan `processing` belum siap.

### Knowledge Base course

Buka course lalu **Knowledge Base**. Halaman tersedia untuk pengguna yang dapat memperbarui course. Source yang dibuat dari sini membawa scope course. Sinkronisasi activity mengirim konten activity terpilih ke DALI.

### WordPress

Menu **WordPress connections** mengelola koneksi dan source WordPress. Credential koneksi termasuk data rahasia.

## Penggunaan chat

Buka halaman Moodle non-Quiz, buka widget, lalu kirim pertanyaan. Plugin meneruskan konteks user, role course, course, activity, tipe halaman, kebijakan knowledge, serta override tampilan. Riwayat dan jawaban ditangani aplikasi DALI.

## Arsitektur singkat

`lib.php` atau hook footer menyusun konteks Moodle dan memuat embed DALI. `api_client.php` menangani knowledge API. `knowledge.php` dan `global_knowledge.php` menyediakan operasi source. Signed fetch memakai credential pengguna berumur terbatas; jangan mengganti kontrak endpoint tanpa memperbarui backend DALI.

## Keamanan dan data

- Widget memuat identitas pengguna, role, konteks course/activity, dan URL halaman.
- Capability mengontrol tampilan widget dan pengelolaan knowledge.
- Quiz selalu dikecualikan untuk mengurangi risiko bantuan saat attempt.
- Avatar hanya disajikan dari system context, tipe gambar yang diizinkan, maksimal 2 MB.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Widget tidak muncul | Enabled, API key, Base URL, capability, halaman Quiz, guest pada `site_wide`. |
| Source tidak pernah siap | Moodle cron untuk mode async dan respons backend. |
| Signed URL gagal | Public base URL, shared secret, masa berlaku, akses jaringan backend. |
| Jawaban di luar course | `knowledge_access_mode` dan konteks course aktif. |
| Tampilan tidak berubah | Nilai appearance, cache Moodle, batas panjang/format warna. |

## Batas dokumentasi

Format file dan source yang benar-benar dapat diproses bergantung pada backend DALI yang terhubung; plugin hanya memvalidasi dan mengirim input yang tersedia pada UI.