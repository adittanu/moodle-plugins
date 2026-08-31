# Quiz Statistics Cache

Calculator cepat yang menulis hasil ke cache Statistics bawaan Moodle.

| Metadata | Nilai |
|---|---|
| Component | `local_quiz_stats_cache` |
| Versi | `2026072000` |
| Moodle minimum | 4.1 (`2022112800`) |

## Instalasi

Salin ke `<Moodle dirroot>/local/quiz_stats_cache`, jalankan upgrade, lalu purge cache. Plugin menambahkan kontrol pada report Statistics, scheduled task, CLI, dan external function.

## Penggunaan browser

Buka Quiz dengan finished attempts lalu **Results > Statistics**. Gunakan **Recalculate (fast)** untuk perubahan biasa. Gunakan force hanya ketika cache diragukan atau change detector tidak menangkap perubahan.

## CLI

```bash
php <Moodle dirroot>/local/quiz_stats_cache/cli/precache.php --fast
```

| Opsi | Fungsi |
|---|---|
| `--fast` | Calculator plugin. |
| `--quizid=ID` | Satu quiz instance. |
| `--force` | Abaikan change detection. |
| `--stale=SECONDS` | Proses cache yang cukup tua. |
| `--dry-run` | Preview tanpa write. |
| `--help` | Bantuan. |

Scheduled task `local_quiz_stats_cache\task\precache_all` menjalankan precache berkala dan dapat dikelola melalui Scheduled tasks Moodle.

## Web service

`local_quiz_stats_cache_get_quiz_stats` menerima quiz ID sesuai parameter external function dan mengembalikan status/cache statistik. Aktifkan Moodle web services serta capability hanya untuk integrator tepercaya.

## Dampak data

Plugin menulis tabel cache core `quiz_statistics` dan `question_statistics`. Plugin tidak dimaksudkan mengubah attempt, answer, atau grade. Kalkulasi harus dibandingkan dengan report core pada data representatif sebelum deployment besar.

## Arsitektur singkat

Hook mendeteksi report Statistics. Endpoint recalculation memanggil `fast_calculator`. CLI dan task melakukan hal yang sama untuk scope berbeda. Lock Statistics core mencegah kalkulasi bersamaan.

## Troubleshooting

| Gejala | Periksa |
|---|---|
| No data | Finished attempts. |
| Quiz not found | Gunakan quiz instance ID, bukan course-module ID. |
| Lock timeout | Proses Statistics lain dan `getstatslocktimeout`. |
| Hasil stale | Force recalculate setelah perubahan grade/quiz. |
| SQL error | Versi DB, data question, log exception. |
| REST ditolak | Service, token, capability, context. |

## Batas dokumentasi

Performa dan kecocokan rumus bergantung pada data quiz serta versi Moodle. Ukur pada salinan data production sebelum mengandalkan precache massal.