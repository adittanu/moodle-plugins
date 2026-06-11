# Panduan Setup Moodle Plugin Auto-Deploy

Panduan lengkap untuk setup auto-deploy plugin Moodle dari local ke server.

---

## Daftar Isi

1. [Overview](#overview)
2. [Prasyarat](#prasyarat)
3. [Setup di Server](#setup-di-server)
4. [Setup Cron Auto-Update](#setup-cron-auto-update)
5. [Workflow Harian](#workflow-harian)
6. [Deploy Manual](#deploy-manual)
7. [Menambah Plugin Baru](#menambah-plugin-baru)
8. [Rollback](#rollback)
9. [Troubleshooting](#troubleshooting)

---

## Overview

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│  Local Dev   │──────▶│   GitHub     │──────▶│   Server     │
│  (Windows)   │ push  │   (Private)  │ pull  │  (Linux/VPS) │
└──────────────┘       └──────────────┘       └──────┬───────┘
                                                     │
                                              ┌──────▼───────┐
                                              │  deploy.sh   │
                                              │  (copy files)│
                                              └──────┬───────┘
                                                     │
                                              ┌──────▼───────┐
                                              │  Moodle 1    │
                                              │  Moodle 2    │
                                              │  Moodle N    │
                                              └──────────────┘
```

**Flow:**
1. Developer edit plugin di local
2. `git push` ke GitHub
3. Server auto-pull setiap 5 menit (cron)
4. Script `deploy.sh` copy file ke semua Moodle instances
5. Developer buka `https://moodle.example.com/admin/` untuk complete upgrade

---

## Prasyarat

### Local (Windows)
- Git installed
- GitHub account (sudah setup)
- Akses ke folder plugin Moodle

### Server (Linux)
- Git installed
- SSH access ke server
- PHP CLI installed (untuk Moodle)
- Crontab access

---

## Setup di Server

### Step 1: Clone Repository

```bash
# Login ke server via SSH
ssh user@server-ip

# Clone repository dengan token (private repo)
git clone https://adittanu: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@github.com/adittanu/moodle-plugins.git /opt/moodle-plugins

# Masuk ke folder
cd /opt/moodle-plugins

# Buat script executable
chmod +x deploy.sh server-auto-update.sh
```

### Step 2: Edit deploy.conf

```bash
nano deploy.conf
```

Tambahkan semua Moodle instance yang ada di server:

```bash
# Format: NAMA|PATH_MOODLE_ROOT|URL_MOODLE
#
# Contoh:
moodle-produksi|/var/www/moodle|https://lms.example.com
moodle-staging|/var/www/moodle-staging|https://staging.example.com
moodle-dev|/var/www/moodle-dev|https://dev.example.com
```

**Penjelasan:**
- `NAMA` → Identifier unik untuk instance (bebas)
- `PATH_MOODLE_ROOT` → Path ke folder root Moodle (tempat `config.php`)
- `URL_MOODLE` → URL akses Moodle (untuk referensi, dipakai di pesan deploy)

### Step 3: Test Deploy

```bash
# Preview perubahan (tanpa copy)
./deploy.sh --dry-run

# Deploy ke semua instance
./deploy.sh

# Deploy ke instance tertentu saja
./deploy.sh moodle-produksi
```

---

## Setup Cron Auto-Update

### Step 1: Edit Crontab

```bash
crontab -e
```

### Step 2: Tambahkan Baris Ini

```bash
# Cek update setiap 5 menit
*/5 * * * * /opt/moodle-plugins/server-auto-update.sh >> /var/log/moodle-plugins-update.log 2>&1
```

### Step 3: Verifikasi

```bash
# Cek cron sudah terdaftar
crontab -l

# Cek log (setelah beberapa menit)
tail -f /var/log/moodle-plugins-update.log
```

### Penjelasan Cron

```
*/5 * * * * = setiap 5 menit
*/10 * * * * = setiap 10 menit
*/15 * * * * = setiap 15 menit
0 * * * * = setiap jam
0 */2 * * * = setiap 2 jam
```

Ganti `*/5` sesuai kebutuhan. Semakin sering = semakin cepat update, tapi lebih berat ke server.

---

## Workflow Harian

### Saat Ada Perubahan Plugin

```bash
# 1. Edit plugin di local (Windows)
#    Contoh: edit D:\Project\dali\moodle-plugins\local\daliwidget\lib.php

# 2. Copy perubahan ke repo (kalau edit di luar repo)
cp -r D:/Project/dali/moodle5/public/local/daliwidget/* D:/Project/dali/moodle-plugins/local/daliwidget/

# 3. Commit dan push
cd D:/Project/dali/moodle-plugins
git add .
git commit -m "daliwidget: fix sync issue"
git push
```

### Setelah Push

```bash
# Server otomatis detect dalam 5 menit
# Cek log di server:
tail -f /var/log/moodle-plugins-update.log

# Atau trigger manual di server:
cd /opt/moodle-plugins
./server-auto-update.sh
```

### Setelah Deploy ke Server

```
Buka browser → https://moodle.example.com/admin/
→ Moodle akan tampilkan "Plugins check"
→ Klik "Upgrade Moodle database now"
→ Selesai!
```

---

## Deploy Manual

### Deploy ke Semua Instance

```bash
cd /opt/moodle-plugins
./deploy.sh
```

### Deploy ke Instance Tertentu

```bash
./deploy.sh moodle-produksi
```

### Preview Perubahan (Dry Run)

```bash
./deploy.sh --dry-run
```

### Force Deploy (Skip Change Detection)

```bash
# Hapus file .last_commit untuk trigger ulang
rm -f .last_commit
./deploy.sh
```

---

## Menambah Plugin Baru

### Step 1: Copy Plugin ke Repo

```bash
# Dari Moodle local
cp -r D:/Project/dali/moodle5/public/local/newplugin D:/Project/dali/moodle-plugins/local/newplugin

# Atau dari mana saja
cp -r /path/to/plugin D:/Project/dali/moodle-plugins/local/newplugin
```

### Step 2: Edit deploy.sh

Tambah mapping di bagian `PLUGINS`:

```bash
declare -A PLUGINS=(
    ["local/aigrading"]="local/aigrading"
    ["local/ailessonplan"]="local/ailessonplan"
    ["local/aiquizgen"]="local/aiquizgen"
    ["local/daliwidget"]="local/daliwidget"
    ["local/newplugin"]="local/newplugin"              # ← TAMBAH INI
    ["mod/quiz/accessrule/webcamguard"]="mod/quiz/accessrule/webcamguard"
)
```

### Step 3: Commit dan Push

```bash
git add .
git commit -m "Add newplugin"
git push
```

### Step 4: Deploy ke Server

```bash
# Otomatis dalam 5 menit, atau manual:
./deploy.sh
```

---

## Rollback

Backup otomatis dibuat setiap deploy dengan format:
```
{plugin_name}.bak.{timestamp}
```

Contoh:
```
/var/www/moodle/local/daliwidget.bak.20260611120000/
```

### Cara Rollback

```bash
# 1. Lihat backup yang tersedia
ls -la /var/www/moodle/local/daliwidget.bak.*

# 2. Hapus versi baru
rm -rf /var/www/moodle/local/daliwidget

# 3. Restore backup
mv /var/www/moodle/local/daliwidget.bak.20260611120000 /var/www/moodle/local/daliwidget

# 4. Set permissions
chown -R www-data:www-data /var/www/moodle/local/daliwidget

# 5. Buka Moodle admin untuk complete rollback
# https://moodle.example.com/admin/
```

---

## Troubleshooting

### Auto-Update Tidak Jalan

```bash
# Cek cron aktif
systemctl status cron

# Cek log
tail -f /var/log/moodle-plugins-update.log

# Cek lock file (mungkin stuck)
rm -f /opt/moodle-plugins/.update.lock

# Test manual
cd /opt/moodle-plugins
./server-auto-update.sh
```

### Deploy Gagal (Permission Denied)

```bash
# Set ownership
chown -R www-data:www-data /var/www/moodle/local/
chown -R www-data:www-data /var/www/moodle/mod/quiz/accessrule/webcamguard

# Atau set permission
chmod -R 755 /var/www/moodle/local/
chmod -R 755 /var/www/moodle/mod/quiz/accessrule/webcamguard
```

### Git Pull Minta Login

```bash
# Set remote URL dengan token
cd /opt/moodle-plugins
git remote set-url origin https://adittanu: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@github.com/adittanu/moodle-plugins.git

# Test
git pull
```

### Plugin Tidak Muncul di Moodle

```bash
# 1. Cek file sudah ter-copy
ls -la /var/www/moodle/local/newplugin/

# 2. Cek version.php ada
cat /var/www/moodle/local/newplugin/version.php

# 3. Buka Moodle admin
# https://moodle.example.com/admin/
# → Notifications → Check for updates

# 4. Kalau masih tidak muncul, purge caches
php /var/www/moodle/admin/cli/purge_caches.php
```

### Token Expired

```bash
# Generate token baru di:
# https://github.com/settings/tokens/new
# Scope: repo (centang yang pertama aja)

# Update remote URL
cd /opt/moodle-plugins
git remote set-url origin https://adittanu:TOKEN_BARU@github.com/adittanu/moodle-plugins.git

# Update .env di local
nano D:/Project/dali/moodle-plugins/.env
```

---

## Command Reference

| Command | Fungsi |
|---------|--------|
| `./deploy.sh` | Deploy ke semua instance |
| `./deploy.sh nama-instance` | Deploy ke instance tertentu |
| `./deploy.sh --dry-run` | Preview tanpa copy |
| `./server-auto-update.sh` | Manual trigger auto-update |
| `git pull` | Pull update terbaru |
| `tail -f deploy.log` | Monitor deploy log |
| `tail -f /var/log/moodle-plugins-update.log` | Monitor auto-update log |

---

## File Structure

```
moodle-plugins/
├── .env                    ← Token GitHub (jangan commit!)
├── .gitignore              ← Git ignore rules
├── deploy.conf             ← Daftar Moodle instances
├── deploy.sh               ← Script deploy
├── server-auto-update.sh   ← Script auto-pull (cron)
├── README.md               ← Dokumentasi repo
├── SETUP_GUIDE.md          ← Panduan ini
├── local/
│   ├── aigrading/          ← Plugin AI Grading
│   ├── ailessonplan/       ← Plugin AI Lesson Plan
│   ├── aiquizgen/          ← Plugin AI Quiz Generator
│   └── daliwidget/         ← Plugin Dali Widget
└── mod/quiz/accessrule/
    └── webcamguard/        ← Plugin Webcam Guard
```

---

## Security Notes

- **Token**: Simpan di `.env`, jangan commit ke git
- **Permissions**: Set ownership ke `www-data` (atau user yang dipakai web server)
- **Backup**: Otomatis dibuat setiap deploy, hapus berkala untuk hemat disk
- **Log**: Monitor log untuk detect masalah early

---

## Support

Kalau ada masalah:
1. Cek log: `tail -f /var/log/moodle-plugins-update.log`
2. Cek deploy log: `tail -f /opt/moodle-plugins/deploy.log`
3. Test manual: `cd /opt/moodle-plugins && ./deploy.sh --dry-run`
4. Cek GitHub: https://github.com/adittanu/moodle-plugins
