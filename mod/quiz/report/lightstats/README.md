# Light Statistics

Report quiz mandiri untuk menghitung statistik dari data attempt tanpa memakai atau mengubah cache Statistics bawaan Moodle.

## Cara kerja

1. Teacher membuka **Quiz > Results > Light statistics**.
2. Teacher menekan **Calculate statistics / Kalkulasi statistik**.
3. Plugin menyimpan job berstatus `queued` di tabel `quiz_lightstats_jobs`.
4. Worker CLI khusus mengambil satu job antrean, menghitung statistik, lalu menyimpan hasil JSON di tabel plugin sendiri.
5. Halaman menampilkan progress dan memuat hasil ketika selesai.
6. CSV dibentuk dari cache plugin; download berikutnya tidak menghitung ulang.

Browser boleh ditutup setelah tombol ditekan. Data attempt baru tidak dihitung otomatis. Halaman hanya memberi peringatan; teacher harus menekan **Recalculate statistics**.

Plugin hanya membaca data quiz. Plugin tidak mengubah nilai, jawaban, attempt, `quiz_statistics`, atau `question_statistics`.

## Instalasi

Unduh paket terbaru:

```bash
wget https://github.com/adittanu/moodle-plugin-releases/releases/latest/download/quiz_lightstats.zip
```

Upload melalui:

```text
Site administration > Plugins > Install plugins
```

Atau ekstrak sehingga file berada di:

```text
<Moodle dirroot>/mod/quiz/report/lightstats/version.php
```

Lalu jalankan:

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/admin/cli/upgrade.php --non-interactive
sudo -u www-data /usr/bin/php <Moodle dirroot>/admin/cli/purge_caches.php
```

`<Moodle dirroot>` adalah folder yang berisi `config.php`, `admin/`, dan `mod/`. Pada instalasi Moodle 5 tertentu, lokasinya dapat berupa `/var/www/moodle/public`.

## Worker wajib dipasang

Tombol hanya membuat antrean. Tanpa worker, progress berhenti pada status **Queued**.

Uji manual terlebih dahulu:

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php
```

Output normal saat antrean kosong:

```text
No queued light statistics jobs.
```

Output normal setelah kalkulasi:

```text
Completed light statistics for quiz 123.
```

### Cron Linux

Buka crontab user web server:

```bash
sudo crontab -u www-data -e
```

Tambahkan:

```cron
* * * * * /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php >> /var/log/moodle-lightstats.log 2>&1
```

Pastikan direktori log dapat ditulis oleh `www-data`, atau gunakan syslog/letak log lain yang sesuai server. Worker memproses satu job per invocation. Jalankan lebih sering hanya bila antrean banyak.

### systemd timer opsional

Cron satu menit sudah cukup untuk mayoritas instalasi. Gunakan systemd timer hanya jika job harus mulai dalam hitungan detik. Command yang dijalankan tetap:

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php
```

Jangan menjalankan worker melalui request HTTP, `nohup` dari tombol, atau Moodle cron. Worker ini sengaja mandiri.

## PHP yang benar

PHP CLI harus kompatibel dengan versi Moodle dan memiliki ekstensi DB yang sama dengan PHP web.

```bash
/usr/bin/php -v
sudo -u www-data /usr/bin/php <Moodle dirroot>/admin/cli/checks.php
```

Jika server memiliki beberapa PHP:

```cron
* * * * * /usr/bin/php8.1 <Moodle41 dirroot>/mod/quiz/report/lightstats/cli/worker.php >> /var/log/moodle41-lightstats.log 2>&1
* * * * * /usr/bin/php8.4 <Moodle51 dirroot>/mod/quiz/report/lightstats/cli/worker.php >> /var/log/moodle51-lightstats.log 2>&1
```

Sesuaikan versi PHP dengan persyaratan resmi instalasi Moodle tersebut.

## Verifikasi end-to-end

1. Login sebagai teacher/admin yang memiliki `mod/quiz:viewreports`.
2. Buka quiz yang sudah memiliki finished attempts.
3. Pilih **Results > Light statistics**.
4. Tekan **Calculate statistics**.
5. Pastikan status menjadi **Queued**.
6. Jalankan worker manual atau tunggu cron.
7. Pastikan progress mencapai `100%` dan dua tabel statistik tampil.
8. Tekan **Download complete statistics CSV**.
9. Tambahkan satu finished attempt baru. Pastikan cache lama tetap tampil disertai peringatan data baru.
10. Tekan **Recalculate statistics** untuk memperbarui cache secara manual.

## Troubleshooting

### Progress berhenti pada Queued

Worker belum berjalan atau memakai path/PHP yang salah:

```bash
sudo -u www-data /usr/bin/php <Moodle dirroot>/mod/quiz/report/lightstats/cli/worker.php
```

Periksa cron:

```bash
sudo crontab -u www-data -l
```

### Worker mengatakan No queued jobs

Tidak ada antrean pada instalasi Moodle yang dibaca worker. Pastikan worker memakai `config.php` dan dirroot situs yang sama dengan halaman tempat tombol ditekan.

### Status Failed

Periksa output worker dan kolom `error` pada tabel `quiz_lightstats_jobs`. Umumnya: koneksi DB, memory limit CLI, data question rusak, atau PHP CLI berbeda dari PHP web.

### Kalkulasi masih berat

Proses sudah terlepas dari browser, tetapi ukuran attempt tetap menentukan waktu dan RAM. Jalankan worker sebagai user web server, cek slow query log, dan ukur pada salinan data production. Jangan mengubah rumus atau menambah index tanpa hasil `EXPLAIN`.

### CSV berisi data lama

Cache sengaja tidak otomatis invalid. Tekan **Recalculate statistics**, lalu tunggu worker selesai.

## Handoff untuk AI/administrator

Saat diminta memasang plugin ini, lakukan tepat urutan berikut:

1. Pastikan ZIP diekstrak ke `<Moodle dirroot>/mod/quiz/report/lightstats`.
2. Jalankan `admin/cli/upgrade.php --non-interactive` dengan PHP CLI situs tersebut.
3. Jalankan `admin/cli/purge_caches.php`.
4. Pasang cron OS untuk `mod/quiz/report/lightstats/cli/worker.php`; jangan memakai Moodle scheduled task.
5. Jalankan worker manual sekali dan pastikan output bukan fatal error.
6. Uji tombol kalkulasi pada quiz yang memiliki finished attempts.
7. Pastikan hasil tampil serta CSV dapat diunduh.
8. Jangan menghubungkan plugin ke tabel cache Statistics core.
