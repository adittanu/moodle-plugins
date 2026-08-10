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

Dokumentasi khusus plugin juga boleh tetap berada di folder plugin bila dibutuhkan Moodle saat distribusi.
