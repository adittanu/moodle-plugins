# Moodle Plugins

Repository public untuk plugin Moodle custom.

## Plugin

| Plugin | Type |
|---|---|
| [`local/siteframe`](local/siteframe/) | Local |
| [`blocks/siteframe`](blocks/siteframe/) | Block |
| [`mod/siteframe`](mod/siteframe/) | Activity |
| [`local/daliwidget`](local/daliwidget/) | Local |
| [`local/aiquizgen`](local/aiquizgen/) | Local |
| [`local/aigrading`](local/aigrading/) | Local |
| [`local/ailessonplan`](local/ailessonplan/) | Local |
| [`local/quiz_stats_cache`](local/quiz_stats_cache/) | Local |
| [`mod/quiz/report/lightstats`](mod/quiz/report/lightstats/) | Quiz report |
| [`mod/quiz/accessrule/webcamguard`](mod/quiz/accessrule/webcamguard/) | Quiz access rule |

## Instalasi / Update

```bash
git clone https://github.com/adittanu/moodle-plugins.git
cd moodle-plugins
git pull --ff-only origin main
```

Copy folder plugin yang diperlukan ke direktori Moodle sesuai path plugin. Setelah update, buka `/admin/` atau jalankan upgrade CLI Moodle.

## Dokumentasi

Semua dokumentasi repository disimpan di [`docs/guides/`](docs/guides/):

- [SiteFrame](docs/guides/siteframe/GUIDE_SITEFRAME.md)
- [Dali AI Widget](docs/guides/daliwidget/GUIDE_DALIWIDGET.md)
- [AI Quiz Generator](docs/guides/aiquizgen/GUIDE_AIQUIZGEN.md)
- [AI Grading](docs/guides/aigrading/GUIDE_AIGRADING.md)
- [AI Lesson Plan](docs/guides/ailessonplan/GUIDE_AILESSONPLAN.md)
- [Quiz Statistics Cache](docs/guides/quiz-stats-cache/GUIDE_QUIZ_STATS_CACHE.md)
- [Light Statistics](docs/guides/lightstats/GUIDE_LIGHTSTATS.md)
- [Webcam Guard](docs/guides/webcamguard/)

## Prompt Instalasi untuk AI

Salin prompt berikut, lalu ganti nilai `MOODLE_PATH`:

```text
Install atau update plugin Moodle dari repository https://github.com/adittanu/moodle-plugins.git ke server ini.

Target Moodle:
MOODLE_PATH=/var/www/moodle

Instruksi:
1. Periksa apakah target plugin sudah ada dan tampilkan versi saat ini.
2. Clone repository jika belum ada; gunakan `git pull --ff-only origin main` jika sudah ada.
3. Salin hanya plugin yang diminta ke `MOODLE_PATH`, tanpa menghapus plugin Moodle lain:
   - Untuk local plugin: `local/<nama-plugin>`
   - Untuk block plugin: `blocks/<nama-plugin>`
   - Untuk mod plugin: `mod/<nama-plugin>`
   - Untuk quiz access rule: `mod/quiz/accessrule/<nama-plugin>`
4. Pertahankan ownership dan permission sesuai plugin Moodle lain di direktori target.
5. Jalankan upgrade Moodle non-interaktif jika `version.php` berubah:
   `php MOODLE_PATH/admin/cli/upgrade.php --non-interactive`
6. Purge cache:
   `php MOODLE_PATH/admin/cli/purge_caches.php`
7. Verifikasi plugin terpasang, versi plugin, dan tidak ada error PHP.
8. Jangan mengubah database, config.php, plugin lain, atau menghapus file tanpa instruksi eksplisit.
9. Jika butuh sudo, minta akses atau tampilkan command yang perlu dijalankan; jangan menebak password.

Plugin yang diminta: <CONTOH: mod/quiz/accessrule/webcamguard>

Laporkan command yang dijalankan, file yang berubah, versi sebelum/sesudah, dan error jika ada.
```

Ganti:

- `MOODLE_PATH` dengan path root Moodle, yaitu folder yang berisi `config.php`.
- `<CONTOH: mod/quiz/accessrule/webcamguard>` dengan path plugin yang ingin dipasang.

Dokumentasi khusus plugin juga boleh tetap berada di folder plugin bila dibutuhkan Moodle saat distribusi.
