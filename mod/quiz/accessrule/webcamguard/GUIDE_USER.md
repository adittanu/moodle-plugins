# Webcam Guard — Panduan Pengguna

Panduan untuk **Admin/Trainer** dan **Peserta**.

---

## Daftar Isi

- [Untuk Peserta](#untuk-peserta)
  - [Apa itu Webcam Guard?](#apa-itu-webcam-guard)
  - [Sebelum Memulai Quiz](#sebelum-memulai-quiz)
  - [Selama Mengerjakan Quiz](#selama-mengerjakan-quiz)
  - [Info dari Trainer](#info-dari-trainer)
  - [Yang Perlu Diperhatikan](#yang-perlu-diperhatikan)
  - [Troubleshooting Peserta](#troubleshooting-peserta)
- [Untuk Admin/Trainer](#untuk-admintrainer)
  - [Dashboard Webcam Guard](#dashboard-webcam-guard)
  - [Mengaktifkan Webcam Guard](#mengaktifkan-webcam-guard)
  - [Melihat Report](#melihat-report)
  - [Memahami Risk Score](#memahami-risk-score)
  - [Review Attempt](#review-attempt)
  - [Live Monitoring](#live-monitoring)
  - [Kirim Info](#kirim-info)
  - [Troubleshooting Admin/Trainer](#troubleshooting-admintrainer)
  - [FAQ](#faq)

---

# Untuk Peserta

## Apa itu Webcam Guard?

Webcam Guard adalah sistem pengawasan otomatis untuk quiz online. Ketika Anda mengerjakan quiz yang menggunakan Webcam Guard:

- **Webcam Anda akan aktif** selama mengerjakan quiz
- **Sistem akan memantau** apakah wajah Anda terlihat di kamera
- **Foto akan diambil** secara otomatis jika terdeteksi pelanggaran
- **Semua data** akan disimpan untuk review oleh admin/trainer Anda
- **Trainer dapat mengirim info** yang muncul di layar Anda

**Yang TIDAK dilakukan Webcam Guard:**
- ❌ Tidak merekam video secara terus-menerus
- ❌ Tidak mengakses file di komputer Anda
- ❌ Tidak mengambil alih layar komputer Anda
- ❌ Tidak memblokir Anda dari quiz (kecuali mode Block untuk identitas)

---

## Sebelum Memulai Quiz

### 1. Persiapan

Sebelum membuka quiz, pastikan:

- ✅ **Webcam berfungsi** — coba buka aplikasi kamera di komputer/HP Anda
- ✅ **Browser terbaru** — gunakan Chrome, Edge, Firefox, atau Safari versi terbaru
- ✅ **Koneksi internet stabil** — monitoring membutuhkan koneksi yang cukup stabil
- ✅ **Pencahayaan cukup** — wajah harus terlihat jelas di kamera
- ✅ **Foto profil Moodle** — jika diminta verifikasi identitas, pastikan foto profil sudah diupload
- ✅ **Kamera depan** — di HP, pastikan menggunakan kamera depan (bukan belakang)

### 2. Membuka Quiz

Ketika Anda membuka quiz yang menggunakan Webcam Guard, akan muncul **jendela pemeriksaan webcam**:

```
┌─────────────────────────────────────────────┐
│  ⚠️ Info Sebelum Mulai               │
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
4. **Tidak ada notifikasi** yang muncul di layar Anda saat pelanggaran biasa
5. **Info dari trainer** bisa muncul di layar Anda (lihat di bawah)

### Preview Kamera

Selama quiz, Anda akan melihat **preview kecil** di pojok kanan bawah layar:

```
┌──────┐
│ cam  │  ← Preview kecil, transparan
│      │
└──────┘
```

Preview ini hanya untuk memastikan webcam Anda aktif. Tidak mempengaruhi pengerjaan quiz.

---

## Info dari Trainer

Selama mengerjakan quiz, trainer dapat mengirim **info langsung** ke layar Anda.

### Seperti Apa Info-nya?

```
┌─────────────────────────────────────────────┐
│                                             │
│         ⚠️ Info dari Trainer          │
│                                             │
│    "Tolong fokus ke layar, terdeteksi       │
│     wajah tidak terlihat beberapa kali"     │
│                                             │
│     Klik di mana saja untuk menutup         │
└─────────────────────────────────────────────┘
```

- Info muncul sebagai **overlay full-screen** dengan border merah
- **Klik di mana saja** atau **tunggu 30 detik** untuk menutup
- Info bersifat **satu kali** — tidak akan muncul lagi setelah ditutup

### Apa yang Harus Dilakukan?

1. **Baca pesan** dengan teliti
2. **Perbaiki perilaku** yang diminta (misal: fokus ke layar, pastikan wajah terlihat)
3. **Klik untuk menutup** info
4. **Lanjutkan quiz** seperti biasa

**Catatan:** Info ini bukan hukuman, tapi teguran dari trainer untuk membantu Anda tetap fokus.

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
- Di HP, gunakan **kamera depan** dan pastikan wajah terlihat
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

### Info muncul terus

1. Baca pesan info dengan teliti
2. Perbaiki apa yang diminta oleh trainer
3. Info hanya muncul sekali per pengiriman
4. Jika merasa info tidak tepat, hubungi trainer setelah quiz selesai

---

# Untuk Admin/Trainer

## Dashboard Webcam Guard

### Mengakses Dashboard

1. Buka mata kuliah yang memiliki quiz dengan Webcam Guard
2. Klik **"Webcam Guard Dashboard"** di menu course
3. Atau: buka salah satu quiz → klik "View Webcam Guard report"

### Halaman Dashboard

Dashboard menampilkan **ringkasan semua quiz** yang menggunakan Webcam Guard di mata kuliah:

| Kolom | Keterangan |
|-------|-----------|
| Nama Quiz | Nama quiz dengan Webcam Guard aktif |
| Total Events | Jumlah event monitoring |
| Total Violations | Jumlah pelanggaran |
| Attempts with Violations | Jumlah attempt yang punya pelanggaran |
| Actions | Link ke report detail per quiz |

---

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

1. **Prasyarat**: LiveKit harus dikonfigurasi oleh admin site
2. Buka **Webcam Guard report**
3. Klik tombol **"Live Monitor"**
4. Pilih **mode monitoring** dari dropdown filter
5. Klik **"Start pilihan"** untuk mulai monitoring
6. Anda akan melihat **grid video** dari peserta yang dipilih

### Indikator Online/Offline

Setiap tile peserta menampilkan badge status:

| Badge | Arti | Keterangan |
|-------|------|-----------|
| 🟢 **Online** | Peserta sedang aktif | Ada monitoring event dalam 60 detik terakhir |
| ⚪ **Offline** | Peserta tidak aktif | Tidak ada event baru, mungkin sudah selesai |

**Catatan:** Peserta harus sedang **membuka halaman quiz** agar statusnya Online. Jika peserta menutup browser atau pindah tab terlalu lama, statusnya akan berubah ke Offline.

### Auto-Reorder (Pengurutan Otomatis)

Tile peserta **otomatis diurutkan** berdasarkan risk score:

- **Risk tertinggi** → pojok kiri atas
- **Risk terendah** → pojok kanan bawah
- Urutan **berubah otomatis** setiap 4 detik saat polling
- **Video stream tetap jalan** saat tile bergeser posisi

Artinya: peserta yang baru saja melakukan pelanggaran akan otomatis naik ke posisi atas, sehingga Anda bisa langsung memperhatikannya.

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

### Navigasi Halaman

Jika peserta lebih dari 20, dashboard menampilkan **navigasi halaman**:
- **Prev / Next** untuk berpindah halaman
- **"X / Y active attempts"** menunjukkan range yang ditampilkan

---

## Kirim Info

### Kirim ke Satu Peserta

1. Buka **Live Monitor**
2. Di setiap tile peserta, ada **input teks** di bagian bawah
3. Ketik pesan info
4. Klik **"Send"**
5. Pesan akan muncul sebagai **overlay di layar peserta**

### Kirim ke Semua Peserta

1. Buka **Live Monitor**
2. Di bagian **atas dashboard**, ada bar **"Kirim Info ke Semua"**
3. Ketik pesan info
4. Klik **"Kirim ke Semua"**
5. Pesan akan dikirim ke **semua peserta yang tampil di grid** (sesuai filter aktif)

### Tips Kirim Info

- **Gunakan filter** sebelum kirim ke semua — misal filter "Hanya yang ada violation" untuk mengingatkan hanya yang melanggar
- **Pesan yang jelas** — contoh: "Tolong fokus ke layar, terdeteksi wajah tidak terlihat"
- **Info bersifat satu kali** — peserta bisa dismiss dengan klik atau tunggu 30 detik
- **Gunakan dengan bijak** — terlalu banyak info bisa mengganggu konsentrasi peserta

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
4. Cek apakah ada peserta yang sedang **aktif mengerjakan** (badge Online)

### Start live monitoring tidak nyambung

1. Pastikan peserta sedang **membuka halaman quiz** (badge harus Online)
2. Jika peserta Offline, minta mereka **refresh halaman quiz**
3. Cek LiveKit config (URL, API key, secret)
4. Cek browser console untuk error WebRTC

### Risk score 0 tapi ada pelanggaran

Risk score 0 berarti tidak ada event dengan severity "violation". Event "info" atau "warning" tidak menambah score. Cek apakah threshold terlalu tinggi.

---

## FAQ

### Untuk Peserta

**Q: Apakah webcam saya direkam?**
A: Tidak. Hanya foto (snapshot) yang diambil saat pelanggaran terdeteksi. Tidak ada video yang direkam.

**Q: Berapa lama data disimpan?**
A: Tergantung setting admin, default 30 hari (bisa diatur 7-365 hari). Setelah itu, data otomatis dihapus.

**Q: Apakah saya bisa menolak webcam?**
A: Jika quiz menggunakan Webcam Guard, Anda harus mengizinkan webcam untuk bisa mengerjakan quiz.

**Q: Apakah foto saya dilihat oleh semua orang?**
A: Tidak. Hanya admin/trainer yang mengampu mata kuliah tersebut yang bisa melihat.

**Q: Apa yang terjadi jika saya menerima info dari trainer?**
A: Info muncul sebagai overlay di layar Anda. Baca pesan, perbaiki perilaku yang diminta, lalu klik untuk menutup. Info tidak mempengaruhi nilai atau status quiz Anda.

**Q: Apakah bisa mengerjakan quiz di HP?**
A: Ya, Webcam Guard mendukung HP (Android dan iOS). Pastikan kamera depan aktif dan pencahayaan cukup.

### Untuk Admin/Trainer

**Q: Apakah saya bisa mengubah setting setelah quiz dimulai?**
A: Setting bisa diubah, tapi hanya berlaku untuk attempt baru. Attempt yang sudah berjalan tetap menggunakan setting lama.

**Q: Apakah Webcam Guard bisa mendeteksi kecurangan?**
A: Webcam Guard adalah alat bantu, bukan pengganti penilaian manusia. Selalu review foto dan konteks sebelum mengambil keputusan.

**Q: Berapa banyak attempt yang bisa dimonitor live?**
A: Maksimal 20 attempt per halaman. Gunakan navigasi halaman untuk melihat lebih banyak.

**Q: Apakah data bisa di-export?**
A: Ya, melalui Moodle Privacy API (Site administration → Privacy).

**Q: Apakah info yang saya kirim akan hilang setelah refresh?**
A: Ya, info bersifat sekali kirim. Setelah peserta dismiss atau 30 detik berlalu, info hilang. Untuk mengirim ulang, kirim lagi.

**Q: Bagaimana cara tahu peserta sedang aktif?**
A: Lihat badge **Online** (hijau) di tile peserta di Live Monitor. Online = ada monitoring event dalam 60 detik terakhir.
