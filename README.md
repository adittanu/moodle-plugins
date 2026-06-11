# Moodle Plugins Repository

Centralized repository untuk semua custom Moodle plugins dengan auto-deploy ke multiple Moodle instances.

---

## Daftar Plugin

| Plugin | Type | Deskripsi |
|--------|------|-----------|
| [`local/aigrading`](local/aigrading/) | Local | AI-powered grading untuk assignment |
| [`local/ailessonplan`](local/ailessonplan/) | Local | AI lesson plan generator |
| [`local/aiquizgen`](local/aiquizgen/) | Local | AI quiz generator |
| [`local/daliwidget`](local/daliwidget/) | Local | Dali widget untuk Moodle |
| [`mod/quiz/accessrule/webcamguard`](mod/quiz/accessrule/webcamguard/) | Quiz Access Rule | Webcam proctoring untuk quiz |

---

## Quick Start

### Option A: Auto Setup (Recommended)

```bash
# 1. Clone
git clone https://adittanu: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@github.com/adittanu/moodle-plugins.git /opt/moodle-plugins
cd /opt/moodle-plugins

# 2. Jalankan setup (interactive)
bash server-setup.sh
```

Script akan bertanya:
- Mau tambah Moodle instances (y/n)
- Path dan URL setiap instance
- Mau setup cron job (y/n)
- Interval update (5/10/15/30/60 menit)

### Option B: Manual Setup

```bash
# 1. Clone
git clone https://adittanu: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@github.com/adittanu/moodle-plugins.git /opt/moodle-plugins
cd /opt/moodle-plugins
chmod +x deploy.sh server-auto-update.sh

# 2. Edit deploy.conf
nano deploy.conf

# 3. Test deploy
./deploy.sh --dry-run

# 4. Setup cron
crontab -e
# Tambah: */5 * * * * /opt/moodle-plugins/server-auto-update.sh >> /var/log/moodle-plugins-update.log 2>&1
```

---

## Workflow

```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│  Local Dev   │──────▶│   GitHub     │──────▶│   Server     │
│  (edit)      │ push  │   (private)  │ pull  │  (auto)      │
└──────────────┘       └──────────────┘       └──────┬───────┘
                                                     │
                                              ┌──────▼───────┐
                                              │  deploy.sh   │
                                              │  (copy files)│
                                              └──────┬───────┘
                                                     │
                                              ┌──────▼───────┐
                                              │   Moodle 1   │
                                              │   Moodle 2   │
                                              │   Moodle N   │
                                              └──────────────┘
```

### Daily Workflow

```bash
# 1. Edit plugin di local
# 2. Copy ke repo (kalau edit di luar)
cp -r D:/Project/dali/moodle5/public/local/daliwidget/* D:/Project/dali/moodle-plugins/local/daliwidget/

# 3. Commit & push
cd D:/Project/dali/moodle-plugins
git add .
git commit -m "daliwidget: fix sync issue"
git push

# 4. Server auto-update dalam 5 menit
# 5. Buka https://moodle.example.com/admin/ → Upgrade database
```

---

## Commands

| Command | Fungsi |
|---------|--------|
| `bash server-setup.sh` | Setup interaktif (pertama kali) |
| `./deploy.sh` | Deploy ke semua instance |
| `./deploy.sh nama-instance` | Deploy ke instance tertentu |
| `./deploy.sh --dry-run` | Preview tanpa copy |
| `./server-auto-update.sh` | Manual trigger auto-update |
| `git pull` | Pull update terbaru |

---

## Menambah Plugin Baru

```bash
# 1. Copy plugin ke repo
cp -r /path/to/plugin local/newplugin

# 2. Edit deploy.sh, tambah mapping
# ["local/newplugin"]="local/newplugin"

# 3. Commit & push
git add .
git commit -m "Add newplugin"
git push
```
---


---

## Rollback

Backup otomatis dibuat setiap deploy:

```bash
# Lihat backup
ls /var/www/moodle/local/plugin.bak.*

# Restore
rm -rf /var/www/moodle/local/plugin
mv /var/www/moodle/local/plugin.bak.20260611120000 /var/www/moodle/local/plugin
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Auto-update tidak jalan | `crontab -l` cek cron, `tail -f /var/log/moodle-plugins-update.log` |
| Permission denied | `chown -R www-data:www-data /var/www/moodle/local/` |
| Git minta login | `git remote set-url origin https://adittanu:TOKEN@github.com/...` |
| Plugin tidak muncul | Buka `/admin/` → Notifications → Check for updates |

---

## File Structure

```
moodle-plugins/
├── .env                    ← GitHub token (jangan commit!)
├── .gitignore
├── deploy.conf             ← Daftar Moodle instances
├── deploy.sh               ← Script deploy
├── server-auto-update.sh   ← Script auto-pull (cron)
├── server-setup.sh         ← Setup interaktif (pertama kali)
├── README.md               ← File ini
├── SETUP_GUIDE.md          ← Panduan lengkap setup
├── local/
│   ├── aigrading/
│   ├── ailessonplan/
│   ├── aiquizgen/
│   └── daliwidget/
└── mod/quiz/accessrule/
    └── webcamguard/
```

---

## Dokumentasi

- **[SETUP_GUIDE.md](SETUP_GUIDE.md)** — Panduan lengkap setup dari nol
- **[deploy.conf](deploy.conf)** — Konfigurasi Moodle instances
- **[GitHub Repo](https://github.com/adittanu/moodle-plugins)** — Repository

---

## License

Proprietary - Internal use only
