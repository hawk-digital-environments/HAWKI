#!/bin/bash

# Script zur Erstellung von Git-Commit-Informationen
# Verwendung: ./create_git_info.sh [ENVIRONMENT] [OUTPUT_FILE]

# Hilfe-Funktion
show_help() {
    cat << EOF
Git-Commit-Info Script v1.0

VERWENDUNG:
    ./create_git_info.sh [OPTIONEN] [ENVIRONMENT] [OUTPUT_FILE]

BESCHREIBUNG:
    Erstellt eine JSON-Datei mit Git-Commit-Informationen vom Haupt-Repository
    und dem HAWKI-Submodule. Die Datei enthält Commit-IDs, Branches, 
    Commit-Messages und Deployment-Informationen.
    
    Das Script läuft aus dem HAWKI-Verzeichnis und ermittelt Git-Informationen
    sowohl für das übergeordnete Repository als auch für das aktuelle HAWKI-Repository.

PARAMETER:
    ENVIRONMENT     Die Deployment-Umgebung (Standard: "unknown")
                    Gültige Werte: local, development, production
    
    OUTPUT_FILE     Pfad zur Ausgabedatei (Standard: "storage/app/git_info.json")

OPTIONEN:
    -h, --help      Zeigt diese Hilfe an und beendet das Script

BEISPIELE:
    ./create_git_info.sh                           # Verwendet Standardwerte
    ./create_git_info.sh local                     # Setzt Umgebung auf "local"
    ./create_git_info.sh production /tmp/git.json  # Benutzerdefinierte Umgebung und Pfad
    ./create_git_info.sh --help                    # Zeigt diese Hilfe an
    
    # Aus dem Haupt-Verzeichnis:
    cd HAWKI && ./create_git_info.sh local

AUSGABE:
    Die JSON-Datei enthält folgende Struktur:
    {
        "main_repo": {
            "commit_id": "abc1234",
            "commit_date": "2025-07-18T10:30:00Z",
            "branch": "main",
            "commit_message": "Add new feature"
        },
        "hawki_submodule": {
            "commit_id": "def5678",
            "commit_date": "2025-07-18T09:15:00Z",
            "branch": "develop",
            "commit_message": "Fix bug"
        },
        "deployment": {
            "environment": "local",
            "deployment_date": "2025-07-18T10:45:00Z"
        }
    }

VORAUSSETZUNGEN:
    - Git muss installiert und verfügbar sein
    - Das Script muss aus dem HAWKI-Verzeichnis ausgeführt werden
    - Das übergeordnete Verzeichnis sollte ein Git-Repository sein
    - Das HAWKI-Verzeichnis sollte ebenfalls ein Git-Repository sein

AUTOR:
    HAWKI Docker Setup Script

EOF
}

# Parameter überprüfen
if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    show_help
    exit 0
fi

# Parameter verarbeiten
ENVIRONMENT=${1:-"unknown"}
OUTPUT_FILE=${2:-"storage/app/git_info.json"}

# Funktion zum Erstellen der Git-Commit-Info-Datei
create_git_commit_info() {
    echo "Erstelle Git-Commit-Info-Datei..."
    
    local commit_id=""
    local commit_date=""
    local branch=""
    local commit_message=""
    local hawki_commit_id=""
    local hawki_commit_date=""
    local hawki_branch=""
    local hawki_commit_message=""
    
    # Git-Commit-ID vom Haupt-Repository ermitteln
    echo "Ermittle Git-Info für Haupt-Repository..."
    cd ..
    if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
        commit_id=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
        commit_date=$(git log -1 --format=%cd --date=iso-strict 2>/dev/null || echo "unknown")
        branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
        commit_message=$(git log -1 --format=%s 2>/dev/null || echo "unknown")
        echo "✅ Haupt-Repository Git-Info ermittelt: $commit_id"
    else
        echo "⚠️  Git ist nicht verfügbar oder nicht in einem Git-Repository"
        commit_id="unknown"
        commit_date="unknown"
        branch="unknown"
        commit_message="unknown"
    fi
    cd HAWKI
    
    # Git-Commit-ID vom HAWKI-Submodule ermitteln
    echo "Ermittle Git-Info für HAWKI-Submodule..."
    if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
        hawki_commit_id=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
        hawki_commit_date=$(git log -1 --format=%cd --date=iso-strict 2>/dev/null || echo "unknown")
        hawki_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
        hawki_commit_message=$(git log -1 --format=%s 2>/dev/null || echo "unknown")
        echo "✅ HAWKI-Submodule Git-Info ermittelt: $hawki_commit_id"
    else
        echo "⚠️  HAWKI-Verzeichnis ist kein Git-Repository"
        hawki_commit_id="unknown"
        hawki_commit_date="unknown"
        hawki_branch="unknown"
        hawki_commit_message="unknown"
    fi
    
    # Erstelle Verzeichnis falls nicht vorhanden
    mkdir -p "$(dirname "$OUTPUT_FILE")"
    
    # Erstelle Git-Info-Datei
    cat > "$OUTPUT_FILE" <<EOF
{
    "main_repo": {
        "commit_id": "$commit_id",
        "commit_date": "$commit_date",
        "branch": "$branch",
        "commit_message": "$commit_message"
    },
    "hawki_submodule": {
        "commit_id": "$hawki_commit_id",
        "commit_date": "$hawki_commit_date",
        "branch": "$hawki_branch",
        "commit_message": "$hawki_commit_message"
    },
    "deployment": {
        "environment": "$ENVIRONMENT",
        "deployment_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
    }
}
EOF
    
    echo "✅ Git-Info-Datei erstellt: $OUTPUT_FILE"
    echo "   Haupt-Repository - Commit-ID: $commit_id"
    echo "   Haupt-Repository - Branch: $branch"
    echo "   Haupt-Repository - Message: $commit_message"
    echo "   HAWKI-Submodule - Commit-ID: $hawki_commit_id"
    echo "   HAWKI-Submodule - Branch: $hawki_branch"
    echo "   HAWKI-Submodule - Message: $hawki_commit_message"
    echo "   Deployment-Zeit: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}

# Hauptfunktion ausführen
create_git_commit_info
