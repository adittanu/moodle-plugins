# SiteFrame Plugin — Panduan Lengkap

Panduan konfigurasi dan penggunaan plugin **SiteFrame** untuk Moodle.

SiteFrame memungkinkan admin dan guru meng-embed halaman web lain ke dalam Moodle menggunakan iframe, dengan berbagai mode tampilan: Full Page, Course Page, Floating Widget, Activity, Modal/Lightbox, dan Block.

Plugin yang dicakup:
- **SiteFrame Local** (`local_siteframe`) — Admin settings, floating widget, full page, course page, modal
- **SiteFrame Activity** (`mod_siteframe`) — Activity module untuk guru tambah di course

Versi 1.0.0

---

## Daftar Isi

- [Bagian 1: Overview SiteFrame](#bagian-1-overview-siteframe)
  - [1.1 Apa itu SiteFrame?](#11-apa-itu-siteframe)
  - [1.2 Display Modes](#12-display-modes)
  - [1.3 Arsitektur Plugin](#13-arsitektur-plugin)
- [Bagian 2: Konfigurasi Admin](#bagian-2-konfigurasi-admin)
  - [2.1 Instalasi Plugin](#21-instalasi-plugin)
  - [2.2 Global Settings](#22-global-settings)
  - [2.3 Domain Allowlist](#23-domain-allowlist)
  - [2.4 Widget Settings](#24-widget-settings)
  - [2.5 Sandbox Flags](#25-sandbox-flags)
  - [2.6 Manage SiteFrame Items](#26-manage-siteframe-items)
  - [2.7 Troubleshooting Admin](#27-troubleshooting-admin)
- [Bagian 3: Penggunaan Trainer/Guru](#bagian-3-penggunaan-trainer-guru)
  - [3.1 Menambahkan SiteFrame sebagai Activity](#31-menambahkan-siteframe-sebagai-activity)
  - [3.2 Menggunakan Floating Widget](#32-menggunakan-floating-widget)
  - [3.3 Menggunakan Course Page](#33-menggunakan-course-page)
  - [3.4 Menggunakan Modal/Lightbox](#34-menggunakan-modallightbox)
  - [3.5 Troubleshooting Guru](#35-troubleshooting-guru)
- [Bagian 4: Display Modes Detail](#bagian-4-display-modes-detail)
  - [4.1 Full Page](#41-full-page)
  - [4.2 Course Page](#42-course-page)
  - [4.3 Floating Widget](#43-floating-widget)
  - [4.4 Activity (mod_siteframe)](#44-activity-modsiteframe)
  - [4.5 Modal/Lightbox](#45-modallightbox)
  - [4.6 Block (Sidebar)](#46-block-sidebar)
- [Bagian 5: Keamanan](#bagian-5-keamanan)
  - [5.1 Domain Allowlist](#51-domain-allowlist)
  - [5.2 Sandbox Attribute](#52-sandbox-attribute)
  - [5.3 Best Practices](#53-best-practices)
- [FAQ](#faq)

---

# Bagian 1: Overview SiteFrame

## 1.1 Apa itu SiteFrame?

SiteFrame adalah plugin Moodle yang memungkinkan Anda **meng-embed halaman web lain** ke dalam Moodle menggunakan teknologi iframe. Plugin ini berguna untuk:

- Menampilkan **dashboard eksternal** (Grafana, Google Data Studio, dll) langsung di Moodle
- Meng-embed **dokumentasi** atau **wiki** dari site lain
- Menampilkan **aplikasi web** pihak ketiga tanpa perlu siswa membuka tab baru
- Integrasi dengan **Google Docs**, **Notion**, **Figma**, dan lainnya

### Siapa yang bisa menggunakan SiteFrame?

| Role | Akses |
|------|-------|
| Admin | Konfigurasi global, manage items, set domain allowlist |
| Trainer/Guru | Tambah activity di course, gunakan widget, configure per-course |
| Siswa | Melihat iframe yang sudah dikonfigurasi |

---

## 1.2 Display Modes

SiteFrame mendukung **6 mode tampilan** yang berbeda:

| Mode | Penjelasan | Siapa yang config |
|------|------------|-------------------|
| **Full Page** | Halaman standalone, iframe full screen | Admin |
| **Course Page** | Halaman khusus di navigasi course | Admin (global) + Guru (per-course) |
| **Floating Widget** | Tombol di pojok yang expand jadi panel iframe | Admin (global URL) |
| **Activity** | Guru tambah di course section sebagai activity | Guru |
| **Modal/Lightbox** | Tombol yang trigger popup iframe | Admin + Guru |
| **Block** | Moodle block di sidebar | Guru |

---

## 1.3 Arsitektur Plugin

SiteFrame terdiri dari 2 komponen utama:

```
+------------------------------------------+
|  local_siteframe                         |
|  - Admin Settings (global)               |
|  - Floating Widget (semua halaman)       |
|  - Full Page view                        |
|  - Course Page view                      |
|  - Modal/Lightbox                        |
|  - Domain Allowlist validation           |
+------------------------------------------+
            |
+------------------------------------------+
|  mod_siteframe                           |
|  - Activity module                       |
|  - Teacher tambah di course section      |
|  - Inline, Fullscreen, Responsive mode   |
+------------------------------------------+
```

---

# Bagian 2: Konfigurasi Admin

## 2.1 Instalasi Plugin

### Langkah Instalasi

1. Copy folder `local/siteframe` ke direktori Moodle `local/`
2. Copy folder `mod/siteframe` ke direktori Moodle `mod/`
3. Login ke Moodle sebagai admin
4. Buka **Site administration** -> **Notifications**
5. Klik **"Upgrade Moodle database now"** untuk menginstall plugin
6. Plugin siap dikonfigurasi

### Verifikasi Instalasi

Setelah instalasi, pastikan:
- Menu **Site administration** -> **Plugins** -> **Local plugins** -> **SiteFrame** muncul
- Menu **Site administration** -> **Plugins** -> **Activity modules** -> **SiteFrame** muncul
- Tidak ada error di halaman Notifications

---

## 2.2 Global Settings

### Cara Mengakses

1. Login ke Moodle sebagai admin
2. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **SiteFrame**
3. Atur setting sesuai kebutuhan
4. Klik **"Save changes"**

### Daftar Settings

| Setting | Default | Penjelasan |
|---------|---------|------------|
| **Enable SiteFrame** | Checked | Aktifkan/nonaktifkan seluruh plugin |
| **Default URL** | (kosong) | URL iframe global yang digunakan widget dan full page |
| **Allowed Domains** | (kosong) | Domain yang diizinkan (satu per baris). Kosong = semua domain |
| **Allow Full Page mode** | Checked | Izinkan mode full page |
| **Allow Course Page mode** | Checked | Izinkan mode course page |
| **Allow Floating Widget mode** | Checked | Izinkan floating widget |
| **Allow Modal/Lightbox mode** | Checked | Izinkan modal/lightbox |
| **Widget Position** | Bottom Right | Posisi widget button (Bottom Right/Left, Top Right/Left) |
| **Widget Icon** | globe | Emoji/icon pada widget button |
| **Widget Title** | SiteFrame | Judul pada widget panel header |
| **Sandbox Flags** | allow-scripts allow-same-origin allow-popups | Flag sandbox iframe |
| **Extra Allowed URLs** | (kosong) | URL tambahan yang bisa dipilih guru (format: label|url) |

### Tips Konfigurasi

- **Default URL**: Isi dengan URL yang ingin ditampilkan secara global (misalnya dashboard utama)
- **Allowed Domains**: Sangat disarankan untuk mengisi domain allowlist demi keamanan
- **Extra Allowed URLs**: Berguna untuk membatasi pilihan guru ke URL yang sudah disetujui admin

---

## 2.3 Domain Allowlist

### Apa itu Domain Allowlist?

Domain Allowlist adalah daftar domain yang diizinkan untuk di-embed. Ini fitur keamanan penting untuk mencegah embedding dari domain berbahaya.

### Cara Konfigurasi

1. Buka **SiteFrame Settings**
2. Di field **Allowed Domains**, masukkan satu domain per baris:

```
example.com
docs.google.com
notion.so
figma.com
```

3. Klik **"Save changes"**

### Cara Kerja

- **Kosong**: Semua domain diizinkan (tidak disarankan untuk production)
- **Ada isi**: Hanya domain dalam list yang diizinkan
- **Subdomain**: Otomatis diizinkan. Jika `example.com` ada di list, maka `app.example.com` juga diizinkan
- **URL yang ditolak**: Muncul pesan error "The URL domain is not in the allowed domains list."

### Contoh

Jika Allowed Domains berisi:
```
example.com
google.com
```

Maka:
- `https://example.com/page` -> [v] Diizinkan
- `https://app.example.com/page` -> [v] Diizinkan (subdomain)
- `https://google.com/docs` -> [v] Diizinkan
- `https://evil.com/page` -> [x] Ditolak
- `https://phishing.com/page` -> [x] Ditolak

---

## 2.4 Widget Settings

### Widget Position

Menentukan di pojok mana floating widget button muncul:

| Posisi | Penjelasan |
|--------|------------|
| **Bottom Right** | Pojok kanan bawah (default, paling umum) |
| **Bottom Left** | Pojok kiri bawah |
| **Top Right** | Pojok kanan atas |
| **Top Left** | Pojok kiri atas |

### Widget Icon

Emoji atau karakter yang muncul pada widget button. Contoh:
- globe (default)
- robot
- chat
- link
- book

### Widget Title

Judul yang muncul di header panel widget. Contoh: "AI Assistant", "Quick Links", "Resources"

---

## 2.5 Sandbox Flags

### Apa itu Sandbox?

Sandbox attribute pada iframe membatasi apa yang bisa dilakukan konten di dalam iframe. Ini fitur keamanan penting.

### Default Flags

```
allow-scripts allow-same-origin allow-popups
```

### Daftar Flag yang Tersedia

| Flag | Penjelasan |
|------|------------|
| `allow-scripts` | Izinkan JavaScript |
| `allow-same-origin` | Izinkan konten treated sebagai same origin |
| `allow-popups` | Izinkan membuka popup/window baru |
| `allow-forms` | Izinkan form submission |
| `allow-modals` | Izinkan modal dialog |
| `allow-downloads` | Izinkan download file |
| `allow-presentation` | Izinkan presentation mode |
| `allow-top-navigation` | Izinkan navigasi ke top-level window |

### Tips

- Tambahkan `allow-forms` jika embedded site butuh form submission (login, search, dll)
- Tambahkan `allow-modals` jika embedded site menggunakan modal dialog
- Jangan hapus `allow-scripts` kecuali Anda yakin embedded site tidak butuh JavaScript
- Jangan hapus `allow-same-origin` karena bisa menyebabkan banyak site tidak berfungsi

---

## 2.6 Manage SiteFrame Items

### Cara Mengakses

1. Buka **Site administration** -> **Plugins** -> **Local plugins** -> **Manage SiteFrame Items**
2. Klik **"Add SiteFrame Item"** untuk menambah item baru

### Field Form

| Field | Required | Penjelasan |
|-------|----------|------------|
| **Name** | Ya | Nama tampilan untuk item ini |
| **URL** | Ya | URL iframe source (harus lolos domain allowlist) |
| **Display Mode** | Ya | Mode tampilan: Full Page, Course Page, Widget, atau Modal |
| **Course ID** | No | 0 = global, >0 = course tertentu |
| **Height** | No | Tinggi iframe dalam px. 0 = auto |
| **Width** | No | Lebar iframe (misal: 100%, 800px) |
| **Scrolling** | No | auto/yes/no |
| **Visible** | No | Show/hide item |

### Edit dan Hapus

- **Edit**: Klik ikon edit di tabel items
- **Hapus**: Klik ikon hapus, konfirmasi penghapusan

---

## 2.7 Troubleshooting Admin

### Plugin tidak muncul di Notifications

- Pastikan folder `local/siteframe` dan `mod/siteframe` ada di direktori Moodle yang benar
- Cek permission folder (harus readable oleh web server)
- Cek error log Moodle

### Settings tidak tersimpan

- Pastikan Anda login sebagai admin dengan capability `moodle/site:config`
- Cek apakah ada error di halaman (merah di atas form)
- Clear cache Moodle: **Site administration** -> **Development** -> **Purge all caches**

### Domain allowlist tidak bekerja

- Pastikan format benar: satu domain per baris, tanpa `http://` atau `https://`
- Cek apakah domain yang dicoba termasuk dalam list (case-insensitive)
- Jika list kosong, semua domain diizinkan

---

# Bagian 3: Penggunaan Trainer/Guru

## 3.1 Menambahkan SiteFrame sebagai Activity

### Cara Menambahkan

1. Buka course yang ingin ditambahkan SiteFrame
2. Klik **"Turn editing on"** (tombol di pojok kanan atas)
3. Di section yang diinginkan, klik **"Add an activity or resource"**
4. Pilih **"SiteFrame"** dari daftar activity
5. Isi form:
   - **Name**: Nama activity (misal: "Google Docs - Materi Kuliah")
   - **Description**: Deskripsi opsional
   - **URL**: URL yang ingin di-embed
   - **Display Mode**: Pilih mode tampilan
   - **Height**: Tinggi iframe (default 600px)
   - **Width**: Lebar iframe (default 100%)
6. Klik **"Save and return to course"** atau **"Save and display"**

### Display Mode untuk Activity

| Mode | Penjelasan |
|------|------------|
| **Inline** | Iframe di dalam halaman activity (paling umum) |
| **Fullscreen** | Iframe fills entire viewport |
| **Responsive** | Iframe menyesuaikan container, min-height 400px |

### Contoh Penggunaan

- Embed Google Docs untuk kolaborasi
- Embed Figma design untuk review
- Embed YouTube playlist
- Embed dashboard monitoring
- Embed wiki dokumentasi

---

## 3.2 Menggunakan Floating Widget

### Apa itu Floating Widget?

Floating Widget adalah tombol kecil di pojok halaman Moodle yang, ketika diklik, membuka panel berisi iframe. Widget ini muncul di **semua halaman Moodle** (kecuali quiz).

### Cara Menggunakan

1. Pastikan admin sudah mengkonfigurasi **Default URL** di settings
2. Lihat pojok kanan bawah halaman Moodle (atau posisi yang dikonfigurasi admin)
3. Klik tombol widget (icon globe atau icon yang dikonfigurasi)
4. Panel akan terbuka dengan iframe
5. Klik tombol lagi atau tekan **Escape** untuk menutup

### Fitur Widget

- **Lazy loading**: Iframe hanya dimuat saat panel dibuka (hemat bandwidth)
- **Keyboard accessible**: Tekan Escape untuk menutup
- **Click outside to close**: Klik di luar panel untuk menutup
- **Responsive**: Menyesuaikan ukuran layar

---

## 3.3 Menggunakan Course Page

### Apa itu Course Page?

Course Page adalah halaman khusus yang muncul di navigasi course, menampilkan iframe yang dikonfigurasi untuk course tersebut.

### Cara Mengakses

1. Buka course yang ingin dilihat
2. Di menu navigasi course (sidebar atau tab), klik **"SiteFrame"**
3. Halaman akan menampilkan iframe yang dikonfigurasi

### Siapa yang Bisa Melihat?

- Semua siswa dan guru yang ter-enroll di course tersebut
- Admin bisa melihat semua course pages

---

## 3.4 Menggunakan Modal/Lightbox

### Apa itu Modal/Lightbox?

Modal/Lightbox adalah popup window yang berisi iframe. Popup ini muncul ketika user mengklik tombol trigger, dan menutup ketika user klik di luar atau tekan Escape.

### Cara Menggunakan

1. Item dengan display mode "Modal" akan menampilkan tombol trigger
2. Klik tombol **"Open SiteFrame"**
3. Modal popup akan muncul dengan iframe di dalamnya
4. Klik **X** atau tekan **Escape** untuk menutup

### Keuntungan Modal

- Tidak mengganggu halaman utama
- Bisa dibuka/ditutup sesuai kebutuhan
- Iframe berhenti loading saat modal ditutup (hemat resource)

---

## 3.5 Troubleshooting Guru

### Activity SiteFrame tidak muncul di "Add activity"

- Hubungi admin untuk memastikan plugin `mod_siteframe` sudah terinstall
- Pastikan Anda memiliki capability `mod/siteframe:addinstance`

### Iframe kosong / tidak memuat

- Kemungkinan target site tidak mengizinkan embedding (X-Frame-Options: DENY)
- Coba buka URL langsung di browser untuk verifikasi
- Hubungi admin site target untuk mengizinkan embedding

### Error "Domain not allowed"

- URL yang Anda masukkan tidak ada di domain allowlist admin
- Hubungi admin untuk menambahkan domain ke allowlist

### Widget tidak muncul

- Pastikan admin sudah meng-enable widget di settings
- Pastikan **Default URL** sudah diisi
- Coba clear cache browser (Ctrl+Shift+Delete)

---

# Bagian 4: Display Modes Detail

## 4.1 Full Page

**URL**: `/local/siteframe/view.php?id=X`

Full Page mode menampilkan iframe yang memenuhi seluruh halaman. Mode ini cocok untuk:
- Dashboard yang butuh layar penuh
- Aplikasi web yang kompleks
- Konten yang tidak perlu di-embed di course

### Akses

- URL bisa diakses langsung oleh user yang memiliki capability `local/siteframe:view`
- Bisa dishare sebagai link di course description, message, dll

---

## 4.2 Course Page

**URL**: `/local/siteframe/course_page.php?courseid=X`

Course Page menampilkan iframe dalam konteks course, dengan header dan navigasi course. Mode ini cocok untuk:
- Resource yang spesifik untuk satu course
- Konten yang perlu diakses bersamaan dengan navigasi course

### Akses

- Muncul di navigasi course untuk semua user yang enroll
- Guru bisa configure item mana yang muncul per course

---

## 4.3 Floating Widget

Floating Widget adalah panel kecil yang bisa dibuka/ditutup di semua halaman Moodle. Mode ini cocok untuk:
- Quick access ke resource penting
- AI assistant atau chatbot
- Link penting yang selalu perlu diakses

### Fitur

- **Lazy loading**: Iframe dimuat hanya saat panel dibuka
- **Persistent**: Muncul di semua halaman (kecuali quiz)
- **Non-intrusif**: Tidak mengganggu konten halaman

---

## 4.4 Activity (mod_siteframe)

Activity mode menambahkan SiteFrame sebagai activity di course section, seperti Quiz atau Assignment. Mode ini cocok untuk:
- Resource yang perlu diakses siswa dalam konteks course
- Konten yang butuh grade atau completion tracking (future)
- Material yang terstruktur dalam course

### Keuntungan

- Terintegrasi dengan course structure
- Bisa di-hide/show per section
- Muncul di course completion report
- Guru bisa set visibility per group

---

## 4.5 Modal/Lightbox

Modal mode menampilkan iframe dalam popup window. Mode ini cocok untuk:
- Referensi yang tidak perlu selalu terlihat
- Konten yang hanya sesekali dibutuhkan
- Preview sebelum membuka full page

### Keuntungan

- Tidak menambah halaman baru
- Iframe berhenti saat modal ditutup
- User bisa lanjut browse tanpa kehilangan konteks

---

## 4.6 Block (Sidebar)

Block mode menambahkan iframe di sidebar Moodle. Mode ini cocok untuk:
- Widget kecil (chat, notifikasi, dll)
- Quick links
- Status dashboard

### Cara Menambahkan Block

1. Buka halaman course atau dashboard
2. Klik **"Turn editing on"**
3. Di sidebar, klik **"Add a block"**
4. Pilih **"SiteFrame"**
5. Konfigurasi URL dan ukuran di block settings

---

# Bagian 5: Keamanan

## 5.1 Domain Allowlist

Domain Allowlist adalah pertahanan pertama melawan embedding berbahaya. **Sangat disarankan** untuk mengisi domain allowlist di production.

### Mengapa Penting?

- Mencegah embedding dari site phishing
- Mencegah embedding dari site berbahaya (malware, dll)
- Membatasi akses ke resource yang disetujui
- Compliance dengan kebijakan keamanan institusi

---

## 5.2 Sandbox Attribute

Sandbox attribute membatasi apa yang bisa dilakukan konten iframe. Konfigurasi default sudah cukup aman untuk kebanyakan kasus.

### Rekomendasi

- **Production**: Gunakan default flags, tambah `allow-forms` hanya jika diperlukan
- **Development**: Bisa lebih permisif untuk testing
- **High security**: Hapus `allow-scripts` jika embedded site tidak butuh JS

---

## 5.3 Best Practices

1. **Selalu gunakan Domain Allowlist** di production
2. **Jangan embed site yang tidak Anda kontrol** tanpa verifikasi keamanan
3. **Gunakan HTTPS** untuk semua URL yang di-embed
4. **Monitor** penggunaan SiteFrame melalui Moodle logs
5. **Review** sandbox flags secara berkala
6. **Edukasi guru** tentang URL yang aman untuk di-embed
7. **Test** embedded site sebelum dishare ke siswa

---

# FAQ

### Q: Apakah SiteFrame gratis?
A: Ya, plugin SiteFrame adalah open source dan gratis digunakan.

### Q: Apakah bisa embed YouTube?
A: Bisa, tapi YouTube sudah punya plugin bawaan Moodle yang lebih optimal. SiteFrame lebih cocok untuk site yang tidak punya plugin Moodle sendiri.

### Q: Apakah siswa bisa mengubah URL iframe?
A: Tidak. Hanya admin dan guru yang bisa mengkonfigurasi URL. Siswa hanya bisa melihat.

### Q: Bagaimana jika embedded site tidak muncul?
A: Kemungkinan besar site tersebut mengirim header `X-Frame-Options: DENY` atau CSP yang melarang embedding. Hubungi admin site tersebut.

### Q: Apakah SiteFrame mengirim data user ke embedded site?
A: Tergantung sandbox flags. Dengan default flags, embedded site tidak bisa mengakses data user Moodle. Tambahkan flag hanya jika diperlukan.

### Q: Bisa embed site yang butuh login?
A: Tergantung. Jika site menggunakan cookie-based auth, mungkin tidak bisa karena sandbox. Jika site support token-based auth atau public URL, bisa.

### Q: Berapa banyak SiteFrame items yang bisa dibuat?
A: Tidak ada batasan, tapi disarankan untuk tidak terlalu banyak demi performa.

### Q: Apakah ada batasan ukuran iframe?
A: Tidak ada batasan teknis, tapi disarankan untuk menyesuaikan dengan layout Moodle. Gunakan width 100% dan height 600px sebagai default.
