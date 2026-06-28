# Webcam Guard — Panduan Pengguna

Panduan untuk **Admin/Trainer** dan **Peserta**.

---

## Daftar Isi

- [Untuk Peserta](#untuk-peserta)
  - [Apa itu Webcam Guard?](#apa-itu-webcam-guard)
  - [Sebelum Memulai Quiz](#sebelum-memulai-quiz)
  - [Selama Mengerjakan Quiz](#selama-mengerjakan-quiz)
  - [Yang Perlu Diperhatikan](#yang-perlu-diperhatikan)
  - [Troubleshooting Peserta](#troubleshooting-peserta)
- [Untuk Admin/Trainer](#untuk-admintrainer)
  - [Mengaktifkan Webcam Guard](#mengaktifkan-webcam-guard)
  - [Melihat Report](#melihat-report)
  - [Memahami Risk Score](#memahami-risk-score)
  - [Review Attempt](#review-attempt)
  - [Live Monitoring](#live-monitoring)
  - [Troubleshooting Admin/Trainer](#troubleshooting-admintrainer)

---

# Untuk Peserta

## Apa itu Webcam Guard?

Webcam Guard adalah sistem pengawasan otomatis untuk quiz online. Ketika Anda mengerjakan quiz yang menggunakan Webcam Guard:

- **Webcam Anda akan aktif** selama mengerjakan quiz
- **Sistem akan memantau** apakah wajah Anda terlihat di kamera
- **Foto akan diambil** secara otomatis jika terdeteksi pelanggaran
- **Semua data** akan disimpan untuk review oleh admin/trainer Anda

**Yang TIDAK dilakukan Webcam Guard:**
- ❌ Tidak merekam video secara terus-menerus
- ❌ Tidak mengakses file di komputer Anda
- ❌ Tidak mengambil alih layar komputer Anda
- ❌ Tidak memblokir Anda dari quiz (kecuali mode Block untuk identitas)

---

## Sebelum Memulai Quiz

### 1. Persiapan

Sebelum membuka quiz, pastikan:

- ✅ **Webcam berfungsi** — coba buka aplikasi kamera di komputer Anda
- ✅ **Browser terbaru** — gunakan Chrome, Edge, Firefox, atau Safari versi terbaru
- ✅ **Koneksi internet stabil** — monitoring membutuhkan koneksi yang cukup stabil
- ✅ **Pencahayaan cukup** — wajah harus terlihat jelas di kamera
- ✅ **Foto profil Moodle** — jika diminta verifikasi identitas, pastikan foto profil sudah diupload

### 2. Membuka Quiz

Ketika Anda membuka quiz yang menggunakan Webcam Guard, akan muncul **jendela pemeriksaan webcam**:

```
┌─────────────────────────────────────────────┐
│  ⚠️ Peringatan Sebelum Mulai               │
│                                             │
│  • Wajah harus terlihat jelas di kamera     │
│  • Tidak boleh ada orang lain di kamera     │
│  • Pindah tab akan dicatat sebagai          │
│    pelanggaran                              │
│  • Menutup kamera akan dicatat sebagai      │
│    pelanggaran                              │
│                                             │
│  ☑ Saya memahami aturan Webcam Guard        │
│                                             │
│  ┌─────────────────────────────────────┐    │
│  │         [Preview Kamera]            │    │
│  │                                     │    │
│  └─────────────────────────────────────┘    │
│                                             │
│  [Periksa Webcam]                           │
└─────────────────────────────────────────────┘
```

### 3. Checklist Sebelum Klik "Periksa Webcam"

| Langkah | Keterangan |
|---------|-----------|
| ☑ Centang persetujuan | Baca dan centang checkbox persetujuan |
| 📷 Izinkan akses kamera | Browser akan meminta izin → klik "Izinkan" |
| 👤 Pastikan wajah terlihat | Wajah harus berada di tengah kamera |
| 💡 Pencahayaan cukup | Hindari backlight (cahaya dari belakang) |

### 4. Verifikasi Identitas (Jika Diaktifkan)

Jika admin/trainer mengaktifkan verifikasi identitas:

- Sistem akan membandingkan wajah Anda di webcam dengan **foto profil Moodle**
- Tampilan **lingkaran similarity** akan menunjukkan persentase kecocokan
- Jika mode **"Flag"**: Anda tetap bisa mulai, tapi akan ditandai di report
- Jika mode **"Block"**: Anda harus cocok dulu sebelum bisa mulai

```
┌─────────────────────┐
│    🟢 85%           │  ← Persentase kecocokan
│   ──────────        │
│  Identity matches   │  ← Status: cocok!
└─────────────────────┘
```

---

## Selama Mengerjakan Quiz

### Apa yang Dipantau?

| Apa | Kapan Dianggap Pelanggaran |
|-----|--------------------------|
| Wajah tidak terlihat | Lebih dari 10 detik (bisa diatur admin/trainer) |
| Lebih dari satu wajah | Lebih dari 3 detik (bisa diatur admin/trainer) |
| Pindah tab/window | Lebih dari 5 detik (bisa diatur admin/trainer) |
| Kamera mati/blokir | Langsung dicatat |

### Apa yang Terjadi Saat Pelanggaran?

1. Sistem mengambil **foto** dari webcam Anda
2. Event **dicatat** di server
3. Admin/Trainer bisa melihat di **report**
4. **Tidak ada notifikasi** yang muncul di layar Anda saat pelanggaran

### Preview Kamera

Selama quiz, Anda akan melihat **preview kecil** di pojok kanan bawah layar:

```
┌──────┐
│ cam  │  ← Preview 160px, transparan
│      │
└──────┘
```

Preview ini hanya untuk memastikan webcam Anda aktif. Tidak mempengaruhi pengerjaan quiz.

---

## Yang Perlu Diperhatikan

### ✅ Yang Boleh Dilakukan

- Mengerjakan quiz seperti biasa
- Sesekali melihat ke bawah (membaca soal)
- Minum air
- Posisi normal di depan komputer

### ❌ Yang Harus Dihindari

- **Pindah ke tab/aplikasi lain** — akan dicatat sebagai pelanggaran
- **Menutup atau memblokir kamera** — akan dicatat sebagai pelanggaran
- **Ada orang lain di kamera** — akan dicatat sebagai pelanggaran
- **Menutup browser** — quiz mungkin tidak tersimpan
- **Mematikan komputer** — quiz mungkin tidak tersimpan

### 💡 Tips

- Gunakan **Chrome atau Edge** untuk performa terbaik
- Pastikan **pencahayaan dari depan** (bukan dari belakang)
- Jangan gunakan **virtual background** — bisa mengganggu deteksi wajah
- Jika webcam error, **refresh halaman** dan coba lagi

---

## Troubleshooting Peserta

### "Webcam tidak ditemukan"

1. Cek apakah webcam terpasang dengan benar
2. Tutup aplikasi lain yang menggunakan webcam (Zoom, Teams, dll)
3. Cek pengaturan browser → izin kamera
4. Coba refresh halaman

### "Izin kamera ditolak"

1. Klik icon 🔒 atau 📷 di address bar browser
2. Ubah izin kamera ke "Izinkan"
3. Refresh halaman

### "Identity does not match"

1. Pastikan foto profil Moodle sudah diupload
2. Pastikan pencahayaan cukup terang
3. Posisikan wajah di tengah kamera
4. Jika mode "Flag": Anda tetap bisa mulai quiz
5. Jika mode "Block": hubungi admin/trainer Anda

### Preview kamera tidak muncul

1. Cek koneksi internet
2. Coba browser lain (Chrome direkomendasikan)
3. Restart browser
4. Hubungi helpdesk IT

---

# Untuk Admin/Trainer

## Mengaktifkan Webcam Guard

### Langkah-langkah

1. **Edit quiz** yang ingin diawasi
2. Scroll ke bagian **"Webcam Guard"**
3. **Centang "Enable Webcam Guard"**
4. Atur setting sesuai kebutuhan:

| Setting | Rekomendasi | Keterangan |
|---------|------------|-----------|
| Snapshot on violation | ✅ Aktif | Ambil foto saat pelanggaran |
| Interval snapshots | Off | Aktifkan jika ingin foto berkala |
| No-face threshold | 10 detik | Berapa lama wajah hilang sebelum pelanggaran |
| Multiple-face threshold | 3 detik | Berapa lama banyak wajah sebelum pelanggaran |
| Blur threshold | 5 detik | Berapa lama pindah tab sebelum pelanggaran |
| Live monitoring | ❌ Nonaktif | Aktifkan jika ingin streaming webcam live |
| Identity verification | ❌ Nonaktif | Aktifkan jika ingin verifikasi wajah |

5. **Simpan quiz**

### Rekomendasi Setting

**Untuk ujian biasa:**
- Snapshot on violation: ✅
- Interval: Off
- Thresholds: default

**Untuk ujian penting:**
- Snapshot on violation: ✅
- Interval: 120 detik
- Identity: Flag mode
- Live monitoring: ✅ (jika ada LiveKit)

---

## Melihat Report

### Membuka Report

1. Buka quiz yang menggunakan Webcam Guard
2. Di halaman quiz, klik **"View Webcam Guard report"**
3. Atau: Quiz → Administration → Webcam Guard report

### Halaman Report

```
┌─────────────────────────────────────────────────────────┐
│  Webcam Guard Report                                    │
│                                                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐ │
│  │ Total    │ │ Total    │ │ Attempts │ │ Top        │ │
│  │ Events   │ │ Violations│ │ w/ Viol. │ │ Violations │ │
│  │   156    │ │    23    │ │    8     │ │ No face:12 │ │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘ │
│                                                         │
│  ┌────────────────────────────────────────────────────┐ │
│  │ Student | Attempt | Status | Events | Viol | Score│ │
│  │─────────│─────────│────────│────────│──────│──────│ │
│  │ Ahmad   │    1    │ Pending│   24   │  3   │  7   │ │
│  │ Budi    │    1    │ Cleared│   18   │  0   │  0   │ │
│  │ Citra   │    1    │Suspicious│ 31   │  8   │  19  │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Filter Status

| Filter | Keterangan |
|--------|-----------|
| All | Semua attempt |
| Pending | Belum direview |
| Cleared | Sudah dinilai aman |
| Suspicious | Dicurigai kecurangan |

---

## Memahami Risk Score

Risk score dihitung dari **jumlah dan jenis pelanggaran**:

| Pelanggaran | Bobot |
|------------|-------|
| Wajah tidak terlihat | 2 poin |
| Banyak wajah | 4 poin |
| Pindah tab/window | 3 poin |
| Kamera berhenti | 5 poin |
| Kamera error | 3 poin |
| Identitas tidak cocok | 4 poin |

### Level Risiko

| Score | Level | Interpretasi |
|-------|-------|-------------|
| 0 | 🟢 Aman | Tidak ada pelanggaran |
| 1-4 | 🔵 Rendah | Pelanggaran minor, kemungkinan tidak disengaja |
| 5-12 | 🟡 Sedang | Perlu diperhatikan, mungkin ada masalah |
| 13+ | 🔴 Tinggi | Perlu review serius, kemungkinan kecurangan |

**Catatan:** Risk score adalah **indikator**, bukan bukti. Selalu review foto dan timeline sebelum mengambil keputusan.

---

## Review Attempt

### Membuka Detail Attempt

1. Di halaman report, klik **"View detail"** pada attempt yang ingin direview
2. Anda akan melihat:
   - **Summary cards**: total events, violations, risk score
   - **Event timeline**: grid foto-foto yang diambil
   - **Review form**: ubah status dan beri komentar

### Event Timeline

Setiap event ditampilkan sebagai **kartu** dengan:
- **Badge**: jenis event (No face, Multiple faces, dll)
- **Status**: Normal (hijau), Warning (kuning), Violation (merah)
- **Foto**: snapshot yang diambil (jika ada)
- **Waktu**: kapan event terjadi

Klik kartu untuk melihat **detail lengkap** termasuk metadata.

### Memberikan Review

1. Pilih **status review**:
   - **Pending**: belum diputuskan
   - **Cleared**: dianggap aman, tidak ada masalah
   - **Suspicious**: dicurigai kecurangan, perlu tindak lanjut

2. Tulis **komentar** (opsional): catatan untuk referensi Anda

3. Klik **"Save review"**

---

## Live Monitoring

### Apa itu Live Monitoring?

Live monitoring memungkinkan Anda **melihat webcam peserta secara langsung** selama mereka mengerjakan quiz. Ini menggunakan teknologi **LiveKit** (WebRTC).

### Cara Menggunakan

1. **Prasyarat**: LiveKit harus dikonfigurasi oleh admin
2. Buka **Webcam Guard report**
3. Klik tombol **"Live Monitor"**
4. Pilih **mode monitoring**:
   - Prioritas risiko
   - Hanya yang ada violation
   - Kamera bermasalah
   - Belum pernah dicek
   - Risk tinggi/sedang/rendah
   - Semua attempt aktif
   - Acak 20 peserta

5. Klik **"Start pilihan"** untuk mulai monitoring
6. Anda akan melihat **grid video** dari peserta yang dipilih

### Mode Filter

| Mode | Keterangan |
|------|-----------|
| Prioritas risiko | Urutkan dari risk score tertinggi |
| Hanya yang ada violation | Filter yang punya pelanggaran |
| Kamera bermasalah | Filter camera_stopped/error |
| Belum pernah dicek | Yang belum pernah dimonitor live |
| Risk tinggi/sedang/rendah | Filter berdasarkan level risk |
| Semua attempt aktif | Tampilkan semua |
| Acak 20 peserta | Pilih 20 secara acak |

---

## Troubleshooting Admin/Trainer

### Report kosong / tidak ada data

1. Pastikan Webcam Guard **enabled** di quiz settings
2. Pastikan ada peserta yang **sudah mulai attempt**
3. Cek filter status → coba "All"
4. Tunggu beberapa menit setelah peserta mulai quiz

### Snapshot tidak muncul

1. Cek quiz settings → "Capture snapshot on violation" centang
2. Cek apakah peserta menggunakan **HTTPS**
3. Cek apakah browser peserta mendukung **getUserMedia**

### Live monitoring tidak bisa

1. Cek LiveKit config di Site administration (admin)
2. Cek quiz settings → "Enable optional live monitoring" centang
3. Cek capability Anda → harus punya `quizaccess/webcamguard:viewreport`
4. Cek apakah ada peserta yang sedang **aktif mengerjakan**

### Risk score 0 tapi ada pelanggaran

Risk score 0 berarti tidak ada event dengan severity "violation". Event "info" atau "warning" tidak menambah score. Cek apakah threshold terlalu tinggi.

---

## FAQ

### Untuk Peserta

**Q: Apakah webcam saya direkam?**
A: Tidak. Hanya foto (snapshot) yang diambil saat pelanggaran terdeteksi. Tidak ada video yang direkam.

**Q: Berapa lama data disimpan?**
A: Tergantung setting admin, default 30 hari. Setelah itu, data otomatis dihapus.

**Q: Apakah saya bisa menolak webcam?**
A: Jika quiz menggunakan Webcam Guard, Anda harus mengizinkan webcam untuk bisa mengerjakan quiz.

**Q: Apakah foto saya dilihat oleh semua orang?**
A: Tidak. Hanya admin/trainer yang mengampu mata kuliah tersebut yang bisa melihat.

### Untuk Admin/Trainer

**Q: Apakah saya bisa mengubah setting setelah quiz dimulai?**
A: Setting bisa diubah, tapi hanya berlaku untuk attempt baru. Attempt yang sudah berjalan tetap menggunakan setting lama.

**Q: Apakah Webcam Guard bisa mendeteksi kecurangan?**
A: Webcam Guard adalah alat bantu, bukan pengganti penilaian manusia. Selalu review foto dan konteks sebelum mengambil keputusan.

**Q: Berapa banyak attempt yang bisa dimonitor live?**
A: Maksimal 20 attempt secara bersamaan di dashboard live.

**Q: Apakah data bisa di-export?**
A: Ya, melalui Moodle Privacy API (Site administration → Privacy).
