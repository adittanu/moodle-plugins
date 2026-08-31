# Moodle Plugins

Kumpulan 11 component Moodle custom, didokumentasikan sebagai sembilan produk.

## Component

| Produk | Component | Path |
|---|---|---|
| Dali AI Widget | `local_daliwidget` | [`local/daliwidget`](local/daliwidget/) |
| SiteFrame | `local_siteframe` | [`local/siteframe`](local/siteframe/) |
| SiteFrame Block | `block_siteframe` | [`blocks/siteframe`](blocks/siteframe/) |
| SiteFrame Activity | `mod_siteframe` | [`mod/siteframe`](mod/siteframe/) |
| AI Quiz Generator | `local_aiquizgen` | [`local/aiquizgen`](local/aiquizgen/) |
| AI Grading | `local_aigrading` | [`local/aigrading`](local/aigrading/) |
| AI Lesson Plan | `local_ailessonplan` | [`local/ailessonplan`](local/ailessonplan/) |
| Quiz Statistics Cache | `local_quiz_stats_cache` | [`local/quiz_stats_cache`](local/quiz_stats_cache/) |
| Light Statistics | `quiz_lightstats` | [`mod/quiz/report/lightstats`](mod/quiz/report/lightstats/) |
| Webcam Guard | `quizaccess_webcamguard` | [`mod/quiz/accessrule/webcamguard`](mod/quiz/accessrule/webcamguard/) |
| DALI Report | `report_dalireport` | [`report/dalireport`](report/dalireport/) |

`mod/quiz/accessrule/webcamguard_bak` adalah backup duplikat, bukan component tambahan atau target dokumentasi.

## Dokumentasi

| Produk | Markdown | PDF |
|---|---|---|
| Dali AI Widget | [Baca](docs/guides/daliwidget/README.md) | [Unduh](docs/guides/daliwidget/manual.pdf) |
| SiteFrame | [Baca](docs/guides/siteframe/README.md) | [Unduh](docs/guides/siteframe/manual.pdf) |
| AI Quiz Generator | [Baca](docs/guides/aiquizgen/README.md) | [Unduh](docs/guides/aiquizgen/manual.pdf) |
| AI Lesson Plan | [Baca](docs/guides/ailessonplan/README.md) | [Unduh](docs/guides/ailessonplan/manual.pdf) |
| AI Grading | [Baca](docs/guides/aigrading/README.md) | [Unduh](docs/guides/aigrading/manual.pdf) |
| Quiz Statistics Cache | [Baca](docs/guides/quiz-stats-cache/README.md) | [Unduh](docs/guides/quiz-stats-cache/manual.pdf) |
| Light Statistics | [Baca](docs/guides/lightstats/README.md) | [Unduh](docs/guides/lightstats/manual.pdf) |
| Webcam Guard | [Baca](docs/guides/webcamguard/README.md) | [Unduh](docs/guides/webcamguard/manual.pdf) |
| DALI Report | [Baca](docs/guides/dalireport/README.md) | [Unduh](docs/guides/dalireport/manual.pdf) |

Bangun ulang semua PDF:

```bash
python moodle-plugins/docs/generate_guides.py
```

Atau satu produk: `python moodle-plugins/docs/generate_guides.py <slug>`.

## Instalasi / update

Salin hanya direktori component yang diperlukan ke path sama di `<Moodle dirroot>`. Jalankan:

```bash
php <Moodle dirroot>/admin/cli/upgrade.php --non-interactive
php <Moodle dirroot>/admin/cli/purge_caches.php
```

Baca panduan produk untuk dependency, worker, HTTPS, dan konfigurasi khusus. Jangan menyalin `webcamguard_bak`.