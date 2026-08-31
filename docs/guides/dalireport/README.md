# DALI Report

Report penggunaan asisten DALI pada scope site atau course.

| Metadata | Nilai |
|---|---|
| Component | `report_dalireport` |
| Versi | `1.1.0` (`2026082100`) |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi dan konfigurasi

Salin ke `<Moodle dirroot>/report/dalireport`, jalankan upgrade, lalu purge cache. Pada pengaturan report isi Base URL dan API key DALI. Keduanya wajib; plugin memanggil `<baseurl>/api/v1/reports` dengan Bearer token.

## Akses

- `report/dalireport:view`: report course; default editing teacher dan manager.
- `report/dalireport:viewsite`: report site; default manager.

Kedua capability berisiko data pribadi dan harus diberikan terbatas.

## Penggunaan

Report site tersedia pada menu Reports. Report course membutuhkan `courseid` dan capability course. Filter yang tersedia: rentang tanggal, course pada scope site, role, response status, dan halaman.

Ringkasan menampilkan session, unique visitor, questions, responses, token usage, feedback positif/negatif, activity harian, kualitas jawaban, topik teratas, serta tabel conversation. CSV memakai filter yang sama dan mengambil halaman API sampai selesai.

## Ekspor CSV

Nilai teks yang diawali `=`, `+`, `-`, atau `@` dinetralkan sebelum ditulis agar spreadsheet tidak mengeksekusi formula. Field API nullable dikonversi menjadi string kosong.

## Arsitektur singkat

`api_client` mengirim request tenant-scoped, memvalidasi key payload wajib, dan menolak HTTP/JSON malformed. `index.php` memvalidasi login/capability, merender filter serta agregat, lalu menangani paging/CSV. Data report tidak disimpan ulang pada tabel plugin.

## Keamanan dan data

API response dapat memuat visitor, role, course, activity, agent, topic, conversation title, last message, status, token usage, dan timestamp. Batasi capability, lindungi API key, gunakan HTTPS, dan perlakukan CSV sebagai data pribadi.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Not configured | Base URL dan API key. |
| Connection failed | Endpoint `/api/v1/reports`, Bearer token, HTTP status, JSON. |
| Malformed report | Backend tidak mengirim summary/session/filter/aggregation wajib. |
| Course kosong | Scope tenant, course filter, rentang tanggal. |
| CSV berbeda dari halaman | Paging API, filter, data berubah saat export. |
| Akses ditolak | Capability course atau site. |

## Batas dokumentasi

Definisi bisnis metric dan retention berasal dari backend DALI; plugin hanya menampilkan payload API tervalidasi.