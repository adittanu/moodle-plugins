# SiteFrame

Paket tiga plugin untuk menampilkan situs yang diizinkan melalui iframe.

| Component | Versi |
|---|---|
| `local_siteframe` | `0.3.0` (`2026080201`) |
| `block_siteframe` | `0.3.0` (`2026080201`) |
| `mod_siteframe` | `0.2.0` (`2026072000`) |

Moodle minimum: 4.1 (`2022112800`).

## Peran component

- `local_siteframe`: allowlist domain, sandbox, item global/course, course page, full page, modal, floating widget.
- `block_siteframe`: iframe sebagai block yang dapat ditambahkan berulang.
- `mod_siteframe`: resource/activity iframe di course.

Ketiga component memakai validasi domain dan konfigurasi `local_siteframe`; pasang `local_siteframe` sebelum memakai block atau activity.

## Instalasi

Salin ketiga direktori ke path Moodle yang sama, jalankan upgrade, lalu purge cache:

- `<Moodle dirroot>/local/siteframe`
- `<Moodle dirroot>/blocks/siteframe`
- `<Moodle dirroot>/mod/siteframe`

## Konfigurasi administrator

Buka **Site administration > Plugins > Local plugins > SiteFrame**.

| Setting | Default | Fungsi |
|---|---|---|
| Enabled | Aktif | Sakelar paket. |
| Allowed domains | Kosong | Hostname yang boleh di-embed, satu per baris. |
| Full page/course page/widget/modal | Aktif | Mengizinkan mode display terkait. |
| Widget position | Bottom right | Posisi tombol floating. |
| Widget icon/title | Globe / SiteFrame | Tampilan widget. |
| Sandbox flags | `allow-scripts allow-same-origin allow-popups` | Kemampuan iframe. |

Isi allowlist dengan hostname, bukan URL lengkap. Gunakan HTTPS. Jangan menambah sandbox permission tanpa kebutuhan situs target.

## Item SiteFrame

Buka **Manage SiteFrame Items**. Setiap item memiliki nama, URL, display mode, scope global atau course, visibility, dimensi, scrolling, dan sort order. Item course mengalahkan item global saat widget memilih kandidat.

Pengguna dengan `local/siteframe:view` melihat course page/widget. Pengelolaan membutuhkan `local/siteframe:manage`.

## Activity

Aktifkan edit course, tambahkan activity **SiteFrame**, isi nama, deskripsi, URL, mode, dimensi, dan scrolling. Activity mendukung deskripsi serta backup Moodle 2. URL tetap harus lolos allowlist.

## Block

Tambahkan block **SiteFrame**, lalu isi URL, tinggi, lebar, dan scrolling. Beberapa instance diperbolehkan. Default tinggi 400 px dan lebar 100% bila konfigurasi kosong.

## Floating widget

Widget memilih satu item visible bermode `widget`: item course lebih dahulu, kemudian item global, diurutkan oleh sort order dan ID. Widget tidak muncul pada attempt/review Quiz, login, atau admin search.

## Arsitektur dan keamanan

`domain_helper` menormalisasi URL, memeriksa allowlist, membersihkan dimensi CSS, dan menghasilkan sandbox attribute. Iframe tidak dapat mengatasi `X-Frame-Options` atau CSP situs target. Allowlist bukan persetujuan pemrosesan data: situs target tetap menerima metadata jaringan/browser pengguna.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Domain not allowed | Hostname exact pada allowlist dan URL valid. |
| Iframe kosong | CSP atau `X-Frame-Options` situs target. |
| Widget tidak muncul | Enabled, allow_widget, capability, item visible, mode dan scope. |
| Block/activity menolak URL | `local_siteframe` terpasang dan domain diizinkan. |
| Ukuran salah | Height integer; width berupa dimensi CSS yang lolos sanitasi. |

## Batas dokumentasi

Perilaku autentikasi di dalam iframe, cookie pihak ketiga, dan izin embedding dikendalikan situs target serta browser.