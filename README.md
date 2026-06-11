# Moodle Plugins Repository

Centralized repository untuk semua custom Moodle plugins. Auto-deploy ke multiple Moodle instances.

## Plugins

| Plugin | Type | Description |
|--------|------|-------------|
| `local/aigrading` | Local | AI-powered grading |
| `local/ailessonplan` | Local | AI lesson plan generator |
| `local/aiquizgen` | Local | AI quiz generator |
| `local/daliwidget` | Local | Dali widget |
| `mod/quiz/accessrule/webcamguard` | Quiz Access | Webcam proctoring |

## Quick Start

### 1. Clone repository di server

```bash
cd /opt
git clone https://github.com/YOUR_USERNAME/moodle-plugins.git
cd moodle-plugins
chmod +x deploy.sh server-auto-update.sh
```

### 2. Edit deploy.conf

```bash
nano deploy.conf
```

Tambahkan Moodle instances:

```
production|/var/www/moodle|https://lms.example.com
staging|/var/www/moodle-staging|https://staging.example.com
```

### 3. Deploy manual (test)

```bash
# Preview perubahan
./deploy.sh --dry-run

# Deploy ke semua instance
./deploy.sh

# Deploy ke instance tertentu
./deploy.sh production
```

### 4. Setup auto-update (cron)

```bash
crontab -e
```

Tambahkan:

```bash
# Cek update setiap 5 menit
*/5 * * * * /opt/moodle-plugins/server-auto-update.sh >> /var/log/moodle-plugins-update.log 2>&1
```

## Workflow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Local Dev  │────▶│   GitHub    │────▶│   Server    │
│  (edit)     │     │  (push)     │     │  (auto-pull)│
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                                        ┌──────▼──────┐
                                        │   Deploy    │
                                        │  (copy to   │
                                        │   Moodle)   │
                                        └──────┬──────┘
                                               │
                                        ┌──────▼──────┐
                                        │   Moodle    │
                                        │  (upgrade)  │
                                        └─────────────┘
```

## Commands

```bash
./deploy.sh              # Deploy ke semua instance
./deploy.sh moodle5      # Deploy ke instance tertentu
./deploy.sh --dry-run    # Preview tanpa copy
```

## Adding New Plugin

1. Copy plugin ke folder yang sesuai di repo:
   ```bash
   cp -r /path/to/plugin local/newplugin
   ```

2. Edit `deploy.sh`, tambah mapping:
   ```bash
   ["local/newplugin"]="local/newplugin"
   ```

3. Commit dan push:
   ```bash
   git add .
   git commit -m "Add newplugin"
   git push
   ```

## Rollback

Backup otomatis dibuat setiap deploy. Untuk rollback:

```bash
# Lihat backup yang tersedia
ls /var/www/moodle/local/aigrading.bak.*

# Restore backup
rm -rf /var/www/moodle/local/aigrading
mv /var/www/moodle/local/aigrading.bak.20260611120000 /var/www/moodle/local/aigrading
```

## Logs

```bash
# Deploy log
tail -f deploy.log

# Auto-update log
tail -f /var/log/moodle-plugins-update.log
```
