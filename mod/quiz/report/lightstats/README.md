# Light Statistics

`quiz_lightstats` (`2026080200`) — report Quiz berbasis antrean yang tidak mengubah cache Statistics core. Moodle 4.1+.

## Instalasi ringkas

Salin direktori ini ke `<Moodle dirroot>/mod/quiz/report/lightstats`, jalankan upgrade Moodle, lalu pasang worker OS:

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php
```

Tanpa worker, job berhenti pada status `queued`.

## Dokumentasi

- [Panduan Markdown](../../../../docs/guides/lightstats/README.md)
- [Panduan PDF](../../../../docs/guides/lightstats/manual.pdf)

Lisensi: GNU GPL v3 atau lebih baru.