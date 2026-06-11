#!/bin/bash
# =============================================
# Moodle Plugin Auto-Update (Server-side)
# =============================================
# Setup sebagai cron job:
#   crontab -e
#   */5 * * * * /path/to/moodle-plugins/server-auto-update.sh >> /var/log/moodle-plugins-update.log 2>&1
#
# Atau manual:
#   ./server-auto-update.sh
# =============================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCK_FILE="${SCRIPT_DIR}/.update.lock"
LAST_COMMIT_FILE="${SCRIPT_DIR}/.last_commit"

# Prevent concurrent runs
if [[ -f "$LOCK_FILE" ]]; then
    # Check if lock is stale (older than 5 minutes)
    if [[ $(find "$LOCK_FILE" -mmin +5 2>/dev/null) ]]; then
        rm -f "$LOCK_FILE"
    else
        exit 0
    fi
fi

trap 'rm -f "$LOCK_FILE"' EXIT
touch "$LOCK_FILE"

cd "$SCRIPT_DIR"

# Fetch latest changes
git fetch origin main 2>/dev/null || git fetch origin master 2>/dev/null

# Get current and remote commit
LOCAL_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "none")
REMOTE_COMMIT=$(git rev-parse origin/main 2>/dev/null || git rev-parse origin/master 2>/dev/null)

# Check if there are updates
if [[ "$LOCAL_COMMIT" == "$REMOTE_COMMIT" ]]; then
    # No updates
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] New updates detected: $LOCAL_COMMIT -> $REMOTE_COMMIT"

# Pull changes
git pull origin main 2>/dev/null || git pull origin master 2>/dev/null

# Run deploy
bash "${SCRIPT_DIR}/deploy.sh"

# Save last deployed commit
echo "$REMOTE_COMMIT" > "$LAST_COMMIT_FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Update completed!"
