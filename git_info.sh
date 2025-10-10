#!/bin/bash

# Funktion zum Erstellen der Git-Commit-Info-Datei
create_git_commit_info() {
    echo "Erstelle Git-Commit-Info-Datei..."
    
    local commit_id=""
    local commit_date=""
    local branch=""
    local commit_message=""
    
    # Git-Commit-ID vom HAWKI-Repository ermitteln
    if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
        commit_id=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
        commit_date=$(git log -1 --format=%cd --date=iso-strict 2>/dev/null || echo "unknown")
        branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
        commit_message=$(git log -1 --format=%s 2>/dev/null || echo "unknown")
        echo "✅ HAWKI Git-Info ermittelt: $commit_id"
    else
        echo "⚠️  Git ist nicht verfügbar oder nicht in einem Git-Repository"
        commit_id="unknown"
        commit_date="unknown"
        branch="unknown"
        commit_message="unknown"
    fi
    
    # Erstelle Git-Info-Datei
    local git_info_file="storage/app/git_info.json"
    
    # Erstelle Verzeichnis falls nicht vorhanden
    mkdir -p "$(dirname "$git_info_file")"
    
    cat > "$git_info_file" <<EOF
{
    "repository": {
        "commit_id": "$commit_id",
        "commit_date": "$commit_date",
        "branch": "$branch",
        "commit_message": "$commit_message"
    },
    "deployment": {
        "environment": "$ENVIRONMENT",
        "deployment_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
    }
}
EOF
    
    echo "✅ Git-Info-Datei erstellt: $git_info_file"
    echo "   Repository - Commit-ID: $commit_id"
    echo "   Repository - Branch: $branch"
    echo "   Repository - Message: $commit_message"
    echo "   Deployment-Zeit: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}

# Funktion ausführen, wenn Skript direkt aufgerufen wird
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    create_git_commit_info
fi
