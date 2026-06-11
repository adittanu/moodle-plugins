#!/bin/bash
# =============================================
# Moodle Plugin Auto-Deploy - Server Setup
# =============================================
# Usage:
#   curl -sL https://raw.githubusercontent.com/adittanu/moodle-plugins/main/server-setup.sh | bash
#   
#   Atau:
#   git clone https://adittanu:TOKEN@github.com/adittanu/moodle-plugins.git /opt/moodle-plugins
#   cd /opt/moodle-plugins
#   bash server-setup.sh
# =============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "============================================="
echo "  Moodle Plugin Auto-Deploy - Server Setup"
echo "============================================="
echo -e "${NC}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if running from the repo directory
if [[ ! -f "${SCRIPT_DIR}/deploy.sh" ]]; then
    echo -e "${RED}ERROR: deploy.sh not found!${NC}"
    echo "Please run this script from the moodle-plugins directory."
    echo ""
    echo "Usage:"
    echo "  git clone https://adittanu:TOKEN@github.com/adittanu/moodle-plugins.git /opt/moodle-plugins"
    echo "  cd /opt/moodle-plugins"
    echo "  bash server-setup.sh"
    exit 1
fi

# Step 1: Make scripts executable
echo -e "${YELLOW}[1/5] Making scripts executable...${NC}"
chmod +x "${SCRIPT_DIR}/deploy.sh"
chmod +x "${SCRIPT_DIR}/server-auto-update.sh"
echo -e "${GREEN}  ✓ Done${NC}"

# Step 2: Create log directory
echo -e "${YELLOW}[2/5] Setting up log files...${NC}"
LOG_DIR="/var/log"
LOG_FILE="${LOG_DIR}/moodle-plugins-update.log"

if [[ -w "$LOG_DIR" ]]; then
    touch "$LOG_FILE"
    echo -e "${GREEN}  ✓ Log file: ${LOG_FILE}${NC}"
else
    LOG_FILE="${SCRIPT_DIR}/update.log"
    touch "$LOG_FILE"
    echo -e "${GREEN}  ✓ Log file: ${LOG_FILE} (fallback, no write access to /var/log)${NC}"
fi

# Step 3: Setup deploy.conf interactively
echo ""
echo -e "${YELLOW}[3/5] Setup deploy.conf${NC}"
echo ""
echo -e "Current deploy.conf:"
echo -e "${BLUE}-----------------------------------${NC}"
cat "${SCRIPT_DIR}/deploy.conf" | grep -v "^#" | grep -v "^$"
echo -e "${BLUE}-----------------------------------${NC}"
echo ""

read -p "Do you want to add Moodle instances now? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "Enter Moodle instances (empty line to finish):"
    echo -e "Format: ${GREEN}NAMA|PATH_MOODLE_ROOT|URL_MOODLE${NC}"
    echo -e "Example: ${GREEN}production|/var/www/moodle/public|https://lms.example.com${NC}"
    echo ""
    
    # Backup existing config
    cp "${SCRIPT_DIR}/deploy.conf" "${SCRIPT_DIR}/deploy.conf.bak"
    
    # Keep comments, remove example entries
    grep "^#" "${SCRIPT_DIR}/deploy.conf.bak" > "${SCRIPT_DIR}/deploy.conf"
    echo "" >> "${SCRIPT_DIR}/deploy.conf"
    
    while true; do
        read -p "> " instance
        if [[ -z "$instance" ]]; then
            break
        fi
        echo "$instance" >> "${SCRIPT_DIR}/deploy.conf"
        echo -e "${GREEN}  ✓ Added: $instance${NC}"
    done
    
    echo ""
    echo -e "${GREEN}  ✓ deploy.conf updated${NC}"
else
    echo -e "${YELLOW}  ⚠ Skipped. Edit deploy.conf manually later.${NC}"
fi

# Step 4: Setup cron
echo ""
echo -e "${YELLOW}[4/5] Setup cron job${NC}"
echo ""

read -p "Setup auto-update cron job? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Select update interval:"
    echo "  1) Every 5 minutes (recommended)"
    echo "  2) Every 10 minutes"
    echo "  3) Every 15 minutes"
    echo "  4) Every 30 minutes"
    echo "  5) Every hour"
    echo ""
    read -p "Choice [1-5]: " interval_choice
    
    case "$interval_choice" in
        1) CRON_INTERVAL="*/5 * * * *" ;;
        2) CRON_INTERVAL="*/10 * * * *" ;;
        3) CRON_INTERVAL="*/15 * * * *" ;;
        4) CRON_INTERVAL="*/30 * * * *" ;;
        5) CRON_INTERVAL="0 * * * *" ;;
        *) CRON_INTERVAL="*/5 * * * *" ;;
    esac
    
    CRON_COMMAND="${CRON_INTERVAL} ${SCRIPT_DIR}/server-auto-update.sh >> ${LOG_FILE} 2>&1"
    
    # Check if cron already exists
    if crontab -l 2>/dev/null | grep -q "server-auto-update.sh"; then
        echo -e "${YELLOW}  ⚠ Cron job already exists. Updating...${NC}"
        crontab -l 2>/dev/null | grep -v "server-auto-update.sh" | crontab -
    fi
    
    # Add cron job
    (crontab -l 2>/dev/null; echo "$CRON_COMMAND") | crontab -
    
    echo -e "${GREEN}  ✓ Cron job added: ${CRON_INTERVAL}${NC}"
    echo -e "${GREEN}  ✓ Log file: ${LOG_FILE}${NC}"
else
    echo -e "${YELLOW}  ⚠ Skipped. Setup cron manually later.${NC}"
fi

# Step 5: Test run
echo ""
echo -e "${YELLOW}[5/5] Testing setup...${NC}"
echo ""

# Test deploy.sh
if "${SCRIPT_DIR}/deploy.sh" --dry-run 2>/dev/null; then
    echo -e "${GREEN}  ✓ deploy.sh works${NC}"
else
    echo -e "${RED}  ✗ deploy.sh failed. Check deploy.conf${NC}"
fi

# Test git remote
if cd "$SCRIPT_DIR" && git remote -v 2>/dev/null | grep -q "origin"; then
    echo -e "${GREEN}  ✓ Git remote configured${NC}"
else
    echo -e "${RED}  ✗ Git remote not found${NC}"
fi

# Summary
echo ""
echo -e "${BLUE}============================================="
echo "  Setup Complete!"
echo "=============================================${NC}"
echo ""
echo -e "Files:"
echo -e "  Config:  ${GREEN}${SCRIPT_DIR}/deploy.conf${NC}"
echo -e "  Deploy:  ${GREEN}${SCRIPT_DIR}/deploy.sh${NC}"
echo -e "  Auto:    ${GREEN}${SCRIPT_DIR}/server-auto-update.sh${NC}"
echo -e "  Log:     ${GREEN}${LOG_FILE}${NC}"
echo ""
echo -e "Commands:"
echo -e "  ${GREEN}./deploy.sh${NC}              Deploy ke semua instance"
echo -e "  ${GREEN}./deploy.sh --dry-run${NC}    Preview tanpa copy"
echo -e "  ${GREEN}./server-auto-update.sh${NC}  Manual trigger auto-update"
echo ""
echo -e "Cron:"
crontab -l 2>/dev/null | grep "server-auto-update.sh" || echo "  (not configured)"
echo ""
echo -e "Next steps:"
echo -e "  1. Edit ${GREEN}deploy.conf${NC} jika belum"
echo -e "  2. Test: ${GREEN}./deploy.sh --dry-run${NC}"
echo -e "  3. Deploy: ${GREEN}./deploy.sh${NC}"
echo -e "  4. Buka ${GREEN}https://moodle.example.com/admin/${NC} untuk upgrade"
echo ""
