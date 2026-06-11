#!/bin/bash
# =============================================
# Moodle Plugin Auto-Deploy Script
# =============================================
# Usage:
#   ./deploy.sh              # Deploy ke semua instance
#   ./deploy.sh moodle5      # Deploy ke instance tertentu
#   ./deploy.sh --dry-run    # Preview tanpa copy
# =============================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_FILE="${SCRIPT_DIR}/deploy.conf"
LOG_FILE="${SCRIPT_DIR}/deploy.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$msg"
    echo "$msg" >> "$LOG_FILE"
}

error() { log "${RED}ERROR: $1${NC}"; }
success() { log "${GREEN}OK: $1${NC}"; }
info() { log "${YELLOW}INFO: $1${NC}"; }

# Check config file
if [[ ! -f "$CONF_FILE" ]]; then
    error "Config file not found: $CONF_FILE"
    exit 1
fi

# Parse arguments
TARGET_INSTANCE=""
DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=true ;;
        *) TARGET_INSTANCE="$arg" ;;
    esac
done

# Plugin mapping: source_path -> target_relative_path
declare -A PLUGINS=(
    ["local/aigrading"]="local/aigrading"
    ["local/ailessonplan"]="local/ailessonplan"
    ["local/aiquizgen"]="local/aiquizgen"
    ["local/daliwidget"]="local/daliwidget"
    ["mod/quiz/accessrule/webcamguard"]="mod/quiz/accessrule/webcamguard"
)

deploy_to_instance() {
    local name="$1"
    local dirroot="$2"
    local wwwroot="$3"
    
    info "Deploying to: $name ($dirroot)"
    
    # Check if Moodle directory exists
    if [[ ! -d "$dirroot" ]]; then
        error "Moodle directory not found: $dirroot"
        return 1
    fi
    
    local changed=0
    
    for src_path in "${!PLUGINS[@]}"; do
        local dest_path="${PLUGINS[$src_path]}"
        local src_dir="${SCRIPT_DIR}/${src_path}"
        local dest_dir="${dirroot}/${dest_path}"
        
        if [[ ! -d "$src_dir" ]]; then
            error "Source not found: $src_dir"
            continue
        fi
        
        # Check if there are actual changes
        if [[ -d "$dest_dir" ]]; then
            # Compare using diff (ignore .git)
            if diff -rq --exclude='.git' "$src_dir" "$dest_dir" > /dev/null 2>&1; then
                continue  # No changes
            fi
        fi
        
        changed=1
        
        if $DRY_RUN; then
            info "[DRY-RUN] Would copy: $src_path -> $dest_dir"
        else
            # Backup existing plugin
            if [[ -d "$dest_dir" ]]; then
                local backup="${dest_dir}.bak.$(date +%Y%m%d%H%M%S)"
                mv "$dest_dir" "$backup"
                info "Backed up: $dest_path -> $(basename $backup)"
            fi
            
            # Copy new version
            mkdir -p "$(dirname "$dest_dir")"
            cp -r "$src_dir" "$dest_dir"
            success "Updated: $dest_path"
        fi
    done
    
    if [[ $changed -eq 0 ]]; then
        info "No changes detected for $name"
        return 0
    fi
    
    if $DRY_RUN; then
        info "[DRY-RUN] Would trigger Moodle upgrade at: $wwwroot/admin/"
    else
        # Set proper permissions (Linux servers)
        if [[ "$(uname)" != "MINGW"* ]] && [[ "$(uname)" != "CYGWIN"* ]]; then
            chown -R www-data:www-data "$dirroot/local/" 2>/dev/null || true
            chown -R www-data:www-data "$dirroot/mod/quiz/accessrule/webcamguard" 2>/dev/null || true
        fi
        
        info "Plugins deployed! Visit $wwwroot/admin/ to complete upgrade."
    fi
}

# Main
log "========================================="
log "Moodle Plugin Deploy"
log "========================================="

# Read config and deploy
while IFS='|' read -r name dirroot wwwroot; do
    # Skip comments and empty lines
    [[ "$name" =~ ^#.*$ ]] && continue
    [[ -z "$name" ]] && continue
    
    # Trim whitespace
    name=$(echo "$name" | xargs)
    dirroot=$(echo "$dirroot" | xargs)
    wwwroot=$(echo "$wwwroot" | xargs)
    
    # Filter by target instance if specified
    if [[ -n "$TARGET_INSTANCE" ]] && [[ "$name" != "$TARGET_INSTANCE" ]]; then
        continue
    fi
    
    deploy_to_instance "$name" "$dirroot" "$wwwroot"
    echo ""
done < "$CONF_FILE"

log "========================================="
log "Deploy completed!"
log "========================================="
