# Light Statistics

Report Quiz mandiri berbasis antrean. Tidak memakai atau mengubah cache Statistics core.

| Metadata | Nilai |
|---|---|
| Component | `quiz_lightstats` |
| Versi | `2026080200` |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi

Salin ke `<Moodle dirroot>/mod/quiz/report/lightstats`, jalankan upgrade, lalu purge cache. Pasang worker OS; tombol browser hanya membuat job.

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php
```

Cron minimum:

```cron
* * * * * /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php >> /var/log/moodle-lightstats.log 2>&1
```

Worker memproses satu job per invocation. PHP CLI harus membaca `config.php`, DB, extension, dan versi PHP yang sama dengan site.

## Penggunaan

1. Buka Quiz dengan finished graded attempts.
2. Pilih **Results > Light statistics**.
3. Klik Calculate statistics.
4. Tunggu job `queued`, `running`, lalu `complete`; browser boleh ditutup.
5. Tinjau summary, tabel question, dan download CSV.
6. Setelah attempt baru, klik Recalculate; cache tidak invalid otomatis.

## Data dan status

Job serta payload JSON disimpan pada `quiz_lightstats_jobs`. CSV dibentuk dari payload complete tanpa kalkulasi ulang. Plugin hanya membaca data Quiz dan tidak mengubah grade, answer, attempt, `quiz_statistics`, atau `question_statistics`.

## Arsitektur singkat

`report.php` membuat job dan merender hasil. `status.php` memberi progress. `cli/worker.php` mengambil satu job. `calculator` menghitung summary/question metrics; `job` menyimpan lifecycle dan hasil.

## Operasi aman

Jalankan worker sebagai user web server. Simpan log di lokasi writable. Jangan menjalankan worker melalui HTTP atau proses `nohup` dari tombol. Gunakan satu menit sebagai default; naikkan frekuensi hanya bila antrean terukur membutuhkan.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| Queued terus | Cron, path worker, PHP CLI, permission. |
| No queued jobs | Worker membaca instalasi/DB berbeda. |
| Failed | Error job, output CLI, memory, DB, data question. |
| CSV lama | Recalculate setelah attempt baru. |
| Kalkulasi berat | Jumlah attempt, slow query log, memory CLI. |
| Tidak ada hasil | Finished graded attempts. |

## Batas dokumentasi

Rumus dan performa mengikuti implementasi `calculator.php`; validasi pada dataset institusi tetap diperlukan.