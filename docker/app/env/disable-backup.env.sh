# Ensure that we disable the backup when running in a docker container.
export BACKUP_DISABLED="true"

# Fail, if backup environment variables are configured
# DB_BACKUP_INTERVAL or DB_BACKUP_INTERVAL_ARGS or DB_BACKUP_DUMPER_BINARY_PATH
if [ -n "$DB_BACKUP_INTERVAL" ] || [ -n "$DB_BACKUP_INTERVAL_ARGS" ] || [ -n "$DB_BACKUP_DUMPER_BINARY_PATH" ]; then
    echo "[ENTRYPOINT] ERROR: Backup environment variables are set, but backup is disabled in a container environment. Please remove DB_BACKUP_INTERVAL, DB_BACKUP_INTERVAL_ARGS, and DB_BACKUP_DUMPER_BINARY_PATH from your environment variables." >&2
    exit 1
fi

export DB_BACKUP_INTERVAL="never"
