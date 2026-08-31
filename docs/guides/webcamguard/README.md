# Webcam Guard

Quiz access rule untuk consent webcam, monitoring browser-side, evidence, live monitoring opsional, dan review pengajar.

| Metadata | Nilai |
|---|---|
| Component | `quizaccess_webcamguard` |
| Versi | `0.9.2` (`2026082500`) |
| Moodle minimum | 4.1 (`2022112800`) |

HTTPS diperlukan oleh browser untuk `getUserMedia()`.

## Instalasi

Salin ke `<Moodle dirroot>/mod/quiz/accessrule/webcamguard`, jalankan upgrade, purge cache, dan pastikan scheduled task aktif. Direktori `webcamguard_bak` bukan plugin terpisah dan tidak termasuk panduan ini.

## Konfigurasi global

| Setting | Default | Fungsi |
|---|---|---|
| LiveKit URL/key/secret | Kosong | Mengaktifkan streaming live opsional. |
| LiveKit token TTL | 300 detik | Masa berlaku token. |
| Retention | 30 hari | Umur event, review, live record, dan snapshot. |

Tanpa LiveKit, preflight, event, snapshot, report, dan review tetap berfungsi.

## Konfigurasi per Quiz

Edit Quiz lalu buka bagian Webcam Guard. Aktifkan rule, snapshot saat violation, interval snapshot, threshold no-face/multiple-face/blur, identity check, serta live monitoring sesuai kebutuhan. Default interval snapshot nonaktif. Threshold terlalu ketat meningkatkan false positive.

## Alur peserta

1. Buka Quiz dan baca consent.
2. Izinkan kamera, jalankan preflight, dan identity check bila aktif.
3. Mulai attempt setelah prasyarat terpenuhi.
4. Pertahankan kamera dan halaman aktif.
5. Bila browser tidak mendukung face detector, monitoring kamera/blur/interval tetap berjalan dan `monitoring_error` dicatat.

Plugin tidak merekam video terus-menerus. Snapshot hanya dibuat sesuai konfigurasi violation/interval.

## Alur pengajar

Buka Webcam Guard report dari Quiz/dashboard. Tinjau event timeline, snapshot, risk indicators, dan status attempt. Tandai review sesuai bukti. Evidence tidak otomatis mengubah hasil Quiz. Live monitoring dapat diminta bila LiveKit dan opsi Quiz aktif.

## Arsitektur singkat

`rule.php` mengintegrasikan Quiz access rule dan preflight. AMD monitor memakai bundle MediaPipe lokal, lalu browser `FaceDetector` sebagai fallback. External functions mencatat event, polling, live request, dan warning. Snapshot memakai Moodle File API. Cleanup task menghapus evidence lebih tua dari retention.

## Capability dan privasi

Konfigurasi, report, serta review memakai capability plugin dan capability Quiz terkait. Data dapat mencakup identitas, event perilaku, snapshot wajah, attempt/user/course ID, review, dan live request. Tetapkan dasar pemrosesan, pemberitahuan peserta, akses terbatas, retention, serta prosedur koreksi sebelum penggunaan produksi.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Kamera tidak tersedia | HTTPS, browser permission, perangkat, kebijakan OS. |
| Face detection terbatas | MediaPipe asset/CSP/WASM; fallback browser. |
| Snapshot tidak ada | Setting violation/interval, File API, event type. |
| Live monitoring tidak tampil | LiveKit URL/key/secret, token TTL, opsi Quiz. |
| Evidence tidak terhapus | Scheduled task dan retention. |
| False positive tinggi | Threshold dan kondisi pencahayaan/kamera. |

## Batas dokumentasi

Deteksi browser bukan bukti kecurangan final. Compatibility browser spesifik tidak diklaim di luar fallback yang tampak pada kode; lakukan pilot pada perangkat peserta.