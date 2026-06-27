# Webcam Guard — Technical Guide

Panduan teknis untuk administrator dan developer.

## Daftar Isi

1. [Persyaratan Sistem](#persyaratan-sistem)
2. [Instalasi](#instalasi)
3. [Konfigurasi Global](#konfigurasi-global)
4. [Konfigurasi Per-Quiz](#konfigurasi-per-quiz)
5. [Arsitektur](#arsitektur)
6. [Database Schema](#database-schema)
7. [Web Service API](#web-service-api)
8. [Capabilities](#capabilities)
9. [LiveKit Integration](#livekit-integration)
10. [Scheduled Tasks](#scheduled-tasks)
11. [Privacy & GDPR](#privacy--gdpr)
12. [Troubleshooting](#troubleshooting)
13. [File Structure](#file-structure)

---

## Persyaratan Sistem

| Komponen | Minimum |
|----------|---------|
| Moodle | 4.1+ (2022112800) |
| PHP | 7.4+ |
| Browser | Chrome 90+, Firefox 90+, Edge 90+, Safari 15+ |
| HTTPS | **Wajib** — getUserMedia() tidak berjalan di HTTP |
| Webcam | Diperlukan di sisi mahasiswa |

**Catatan Browser:**
- MediaPipe Face Detection (primary) membutuhkan WASM + SIMD support
- Native FaceDetector API (fallback) tersedia di Chrome/Edge saja
- Firefox/Safari: fitur deteksi wajah terbatas, monitoring blur/camera tetap jalan

---

## Instalasi

### Manual

```bash
# Copy plugin ke direktori Moodle
cp -r webcamguard/ /path/to/moodle/mod/quiz/accessrule/webcamguard/

# Jalankan upgrade
php /path/to/moodle/admin/cli/upgrade.php
```

### Via Git

```bash
cd /path/to/moodle/mod/quiz/accessrule/
git clone https://github.com/adittanu/moodle-plugins.git temp
cp -r temp/mod/quiz/accessrule/webcamguard/ ./webcamguard/
rm -rf temp
php /path/to/moodle/admin/cli/upgrade.php
```

### Verifikasi

Buka **Site administration → Plugins → Activity modules → Quiz → Manage access rules**. Pastikan "Webcam Guard" muncul di daftar.

---

## Konfigurasi Global

**Lokasi:** Site administration → Module settings → Webcam Guard

### LiveKit (Opsional)

| Setting | Keterangan | Contoh |
|---------|-----------|--------|
| LiveKit WebSocket URL | URL server LiveKit | `wss://livekit.example.com` |
| LiveKit API Key | API key dari dashboard LiveKit | `APIxxxxxxxxxx` |
| LiveKit API Secret | Secret untuk sign token | `xxxxxxxxxx` |
| Token TTL | Durasi token live (detik) | `300` |

Jika LiveKit tidak dikonfigurasi, semua fitur lain tetap berjalan. Tombol live monitoring tidak ditampilkan.

### Data Retention

| Setting | Keterangan | Default |
|---------|-----------|---------|
| Retention period | Berapa lama data disimpan sebelum dihapus otomatis | 30 hari |

Pilihan: 7, 14, 30, 60, 90, 180, 365 hari.

### HTTPS Warning

Jika site menggunakan HTTP, admin akan melihat peringatan bahwa fitur kamera tidak akan berfungsi.

---

## Konfigurasi Per-Quiz

**Lokasi:** Edit quiz → Webcam Guard section

| Setting | Tipe | Default | Keterangan |
|---------|------|---------|-----------|
| Enable Webcam Guard | Checkbox | Off | Aktifkan monitoring untuk quiz ini |
| Capture snapshot on violation | Checkbox | On | Ambil foto saat pelanggaran terdeteksi |
| Interval snapshots | Select | Off | Ambil foto berkala (60/120/300 detik) |
| No-face threshold | Number | 10 detik | Berapa lama wajah tidak terdeteksi sebelum dianggap pelanggaran |
| Multiple-face threshold | Number | 3 detik | Berapa lama lebih dari satu wajah sebelum dianggap pelanggaran |
| Tab/window blur threshold | Number | 5 detik | Berapa lama pindah tab sebelum dianggap pelanggaran |
| Enable optional live monitoring | Checkbox | Off | Izinkan guru meminta streaming webcam live |
| Verify identity with profile picture | Checkbox | Off | Bandingkan wajah webcam dengan foto profil |
| Identity match threshold | Number | 60 | Nilai 30-90, semakin kecil semakin ketat |
| If identity does not match | Select | Flag | Flag (tandai) atau Block (halangi) |

---

## Arsitektur

```
┌─────────────────────────────────────────────┐
│         Moodle Quiz Access Rule System       │
└──────────────────┬──────────────────────────┘
                   │
       ┌───────────▼───────────┐
       │      rule.php         │
       │  (Access Rule Class)  │
       └──┬─────┬─────┬───────┘
          │     │     │
  ┌───────┘     │     └───────┐
  ▼             ▼             ▼
Settings    Preflight     Attempt Page
(CRUD)      (Identity)    (Monitor + Live)
              │             │
      ┌───────┤       ┌─────┼──────┐
      ▼       ▼       ▼     ▼      ▼
  preflight  faceapi  monitor live_  live_
    .js       .js      .js  student dashboard
                           .js     .js
```

### Alur Monitoring

```
1. Admin aktifkan Webcam Guard di quiz settings
2. Mahasiswa buka quiz → preflight modal muncul
3. Mahasiswa centang consent → klik "Check webcam"
4. Webcam aktif → (opsional) verifikasi identitas
5. Mahasiswa mulai quiz → monitor.js berjalan
6. Deteksi wajah tiap 1 detik (MediaPipe → native fallback)
7. Pelanggaran dikirim ke server via AJAX (throttle 5s/type)
8. Snapshot diambil sesuai setting (violation/interval)
9. Guru buka report → lihat timeline, risk score, foto
10. (Opsional) Guru minta live streaming via LiveKit
11. Cleanup task hapus data > retention period
```

---

## Database Schema

### quizaccess_wg_config

Konfigurasi per-quiz. Kolom utama: `quizid` (unique), `enabled`, `snapshotonviolation`, `intervalseconds`, `nofacethreshold`, `multifacethreshold`, `blurthreshold`, `identityenabled`, `identitythreshold`, `identitymode`, `liveenabled`.

### quizaccess_wg_events

Event monitoring. Kolom utama: `id`, `courseid`, `cmid`, `quizid`, `attemptid`, `userid`, `eventtype`, `durationms`, `severity`, `metadata` (JSON), `hassnapshot`, `clienttime`, `timecreated`.

Index: `(quizid)`, `(attemptid)`, `(userid)`, `(eventtype)`, `(timecreated)`.

### quizaccess_wg_reviews

Status review guru. Kolom: `id`, `quizid`, `attemptid`, `userid`, `status` (pending/cleared/suspicious), `reviewedby`, `reviewcomment`, `timecreated`, `timemodified`.

Unique index: `(attemptid)`.

### quizaccess_wg_live

Request live monitoring. Kolom: `id`, `courseid`, `cmid`, `quizid`, `attemptid`, `userid`, `requestedby`, `roomname`, `status` (requested/active/stopped), `timecreated`, `timemodified`, `expiresat`.

Composite index: `(attemptid, status, expiresat)`.

---

## Web Service API

| Function | Method | Capability | Keterangan |
|----------|--------|-----------|------------|
| `quizaccess_webcamguard_log_event` | Write | mod/quiz:attempt | Mahasiswa kirim event monitoring |
| `quizaccess_webcamguard_request_live` | Write | viewreport | Guru mulai/stop live monitoring |
| `quizaccess_webcamguard_poll_live` | Read | mod/quiz:attempt | Mahasiswa cek request live |
| `quizaccess_webcamguard_poll_live_stats` | Read | viewdashboard | Dashboard guru refresh stats |

---

## Capabilities

| Capability | Role Default | Keterangan |
|-----------|-------------|-----------|
| `quizaccess/webcamguard:configure` | Editing Teacher, Manager | Mengkonfigurasi Webcam Guard di quiz settings |
| `quizaccess/webcamguard:viewreport` | Teacher, Editing Teacher, Manager | Melihat report dan snapshot |
| `quizaccess/webcamguard:reviewattempts` | Teacher, Editing Teacher, Manager | Mengubah status review (pending/cleared/suspicious) |

---

## LiveKit Integration

### Setup

1. Buat akun di [LiveKit Cloud](https://cloud.livekit.io/) atau self-host
2. Dapatkan WebSocket URL, API Key, dan API Secret
3. Masukkan di Site administration → Module settings → Webcam Guard
4. Aktifkan "Enable optional live monitoring" di quiz settings

### Room Naming

Format: `wg-q{quizid}-a{attemptid}-{random_hex}`

### Token

- HS256 JWT dengan claims: `video.roomJoin`, `room`, `canPublish`, `canSubscribe`
- Teacher: subscribe-only token
- Student: publish-only token
- TTL: configurable, default 300 detik, clamp 60-3600

---

## Scheduled Tasks

### Cleanup Task

- **Nama:** Clean up old Webcam Guard evidence
- **Jadwal:** Setiap hari jam 03:17
- **Aksi:** Hapus events, reviews, live records, dan snapshot files yang lebih lama dari retention period
- **Retention:** Dibaca dari admin setting, default 30 hari
- **Batch:** 1000 records per iterasi (mencegah OOM)

---

## Privacy & GDPR

Plugin mengimplementasikan Moodle Privacy API secara lengkap:

- **Metadata provider:** Mendeklarasikan data personal di 3 tabel + file snapshots
- **Export:** Mengeksport events, reviews, live records, dan snapshot files per user per context
- **Delete:** Menghapus data per user atau per context
- **get_contexts_for_userid:** Mencari context yang mengandung data user
- **get_users_in_context:** Mencari user yang punya data di context

---

## Troubleshooting

### Webcam tidak muncul di mahasiswa

1. Pastikan site menggunakan **HTTPS** (bukan HTTP)
2. Cek browser console untuk error getUserMedia
3. Pastikan mahasiswa mengizinkan akses kamera di browser
4. Cek quiz settings → Webcam Guard enabled

### MediaPipe tidak jalan

- Cek browser support: Chrome 90+, Edge 90+
- Firefox/Safari: fallback ke native FaceDetector API
- Jika tidak ada detector: monitoring blur/camera tetap jalan, event `monitoring_error` dicatat

### Live monitoring tidak aktif

1. Cek LiveKit config di Site administration (URL, API key, secret harus terisi)
2. Cek quiz settings → "Enable optional live monitoring" centang
3. Cek capability `quizaccess/webcamguard:viewreport`

### Snapshot tidak tersimpan

1. Cek quiz settings → "Capture snapshot on violation" centang
2. Cek Moodle file storage (disk space)
3. Max size: 2MB per snapshot

### Data tidak dihapus otomatis

1. Cek Scheduled Tasks → "Clean up old Webcam Guard evidence" enabled
2. Cek retention setting di Site administration
3. Jalankan manual: `php admin/cli/scheduled_task.php --execute='\quizaccess_webcamguard\task\cleanup'`

---

## File Structure

```
webcamguard/
├── rule.php                    # Main access rule class (854 lines)
├── locallib.php                # Shared helper functions
├── settings.php                # Admin settings page
├── attempt.php                 # Teacher attempt detail page
├── report.php                  # Teacher report page
├── lib.php                     # Plugin file callback (snapshots)
├── version.php                 # Version info
├── styles.css                  # All CSS (preflight, report, dashboard)
├── thirdpartylibs.xml          # Third-party library declarations
├── README.md                   # Brief readme
├── GUIDE_TECHNICAL.md          # This file
├── GUIDE_USER.md               # User guide (non-technical)
├── amd/
│   ├── src/                    # Source JS modules
│   │   ├── preflight.js        # Identity verification (face-api.js)
│   │   ├── monitor.js          # Runtime monitoring (MediaPipe)
│   │   ├── live_student.js     # Student LiveKit publisher
│   │   ├── live_teacher.js     # Teacher single-viewer
│   │   └── live_dashboard.js   # Teacher multi-attempt dashboard
│   └── build/                  # Minified builds
├── classes/
│   ├── external/               # Web service APIs
│   │   ├── log_event.php
│   │   ├── request_live.php
│   │   ├── poll_live.php
│   │   └── poll_live_stats.php
│   ├── livekit/
│   │   └── token_service.php   # JWT token generator
│   ├── output/
│   │   ├── attempt_detail.php  # Attempt detail HTML builder
│   │   └── report_page.php     # Report page HTML builder
│   ├── privacy/
│   │   └── provider.php        # GDPR privacy provider
│   └── task/
│       └── cleanup.php         # Scheduled cleanup task
├── db/
│   ├── install.xml             # Database schema
│   ├── access.php              # Capabilities
│   ├── services.php            # Web service registration
│   ├── tasks.php               # Scheduled task registration
│   └── upgrade.php             # Upgrade steps
├── lang/
│   ├── en/quizaccess_webcamguard.php
│   └── id/quizaccess_webcamguard.php
├── faceapi/                    # face-api.js v0.22.2 + models
├── mediapipe/                  # MediaPipe Face Detection v0.4
├── livekit/                    # LiveKit Client SDK v2.18.9
└── pix/                        # Images (live-monitor-empty.png)
```
