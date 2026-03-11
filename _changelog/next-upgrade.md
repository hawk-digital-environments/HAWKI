# Upgrading to vx.y.z

## Docker Deployment

This release contains **significant changes** to the Docker setup. Please read the following carefully before upgrading.

> TLDR
> If you did not modify the `_docker_production` files at all, you can simply copy the "docker-compose.yml" and modify your ".env" to add the new variables (described below).

### Standalone nginx container removed

The separate `nginx` service and its `nginx.default.conf` configuration file have been removed from the
`_docker_production` setup. nginx is now bundled **inside** the `app` container via the new
[`neunerlei/php-nginx`](https://github.com/Neunerlei/docker-images/blob/main/docs/php-nginx.md) base image.

**What you need to do:**

1. Remove the `nginx` service block from your `docker-compose.yml`.
2. Remove any reference to the `nginx.default.conf` file — it no longer exists and is no longer needed.
3. Remove the `php_socket` named volume from the `volumes:` section at the bottom of your `docker-compose.yml`.
4. Move your SSL certificate mount, which is now mounted directly into the `app` container via `./certs:/container/custom/certs`.
5. Add the following port mapping to the `app` service (these were previously on the `nginx` service):
   ```yaml
   ports:
     - ${DOCKER_PROJECT_IP:-127.0.0.1}:80:80
     - ${DOCKER_PROJECT_IP:-127.0.0.1}:443:443
   ```

### New `.env` variables — `APP_HOST` and `APP_PROTOCOL`

`APP_URL` is no longer the primary way to tell HAWKI about its public address. It is now derived automatically from
two new variables:

| Variable           | Example                  | Description                                                            |
|--------------------|--------------------------|------------------------------------------------------------------------|
| `APP_HOST`         | `yourdomain.example.com` | The public hostname without protocol or trailing slash                 |
| `APP_PROTOCOL`     | `https`                  | Either `http` or `https`                                               |
| `DB_ROOT_PASSWORD` | `asd89!AertTw1x`         | The root password for the MySQL database (required by the MySQL image) |

**What you need to do:**

1. Replace `APP_URL=https://yourdomain.example.com` in your `.env` with:
   ```
   APP_HOST=yourdomain.example.com
   APP_PROTOCOL=https
   ```
2. Add the following infrastructure variables that were previously auto-set but must now be explicit:
   ```
   REVERB_HOST=reverb
   ```

Compare your existing `.env` with the updated `_docker_production/.env` template for the full reference.

### `queue` and `reverb` services: new `PHP_WORKER_COMMAND` variable

The `command:` directive on the `queue` and `reverb` services has been replaced by the `PHP_WORKER_COMMAND`
environment variable, which is the preferred way to pass a worker command to the base image.

**What you need to do:** In your `docker-compose.yml`, for the `queue` service replace:

```yaml
command: [ 'php artisan queue:work --queue=default,mails,message_broadcast --tries=3 --timeout=90' ]
```

with:

```yaml
environment:
    PHP_WORKER_COMMAND: "php artisan queue:work --queue=default,mails,message_broadcast --tries=3 --timeout=90"
```

And for the `reverb` service replace:

```yaml
command: [ 'php artisan reverb:start' ]
```

with:

```yaml
environment:
    PHP_WORKER_COMMAND: "php artisan reverb:start"
```

### `mysql` service: upgrade to MySQL 8.4

Firstly, we have upgraded the MySQL image from version 8 to 8.4 which is the current LTS version. Generally, this should be a drop in replacement,
however the auth plugin: `mysql_native_password` (we used previously) is now deprecated and will no longer work in version 9.

Therefore, we have to switch to `caching_sha2_password` which means we need to update the tables containing the users in the database.
However, we also used `MYSQL_RANDOM_ROOT_PASSWORD: '1'` meaning there is no easy way for the root user to log in and update the tables, leaving us with the second change; we introduced a new environment variable `DB_ROOT_PASSWORD` which is required by the MySQL image and allows us to log in as root and update the tables.

"But what about our data?" you might ask. Don't worry, we will guide you through the upgrade process, and if you follow the steps carefully, your data will be safe.

#### Upgrade the compose file

Before we upgrade your data, we must first upgrade the compose file to use the new MySQL image and add the new environment variable.

1. In your `docker-compose.yml`, for the `mysql` service replace:
   ```yaml
   image: mysql:8
   ```
   with:
   ```yaml
   image: mysql:8.4
   ```
2. Add the new environment variable `DB_ROOT_PASSWORD` above `DB_PASSWORD` in the `mysql` service:
   ```yaml
   environment:
     DB_ROOT_PASSWORD: your_root_password_here
     DB_USERNAME: your_db_username_here
     DB_PASSWORD: your_db_password_here
     DB_DATABASE: your_db_name_here
   ```
3. Remove the `MYSQL_RANDOM_ROOT_PASSWORD: '1'` line from the `mysql` service, as it is no longer needed.
4. Remove the now outdated: `- --default-authentication-plugin=mysql_native_password` line from the `command` block of the `mysql` service.

#### Upgrading your data automatically

If you like to automate things, here is a bash script that does the upgrade for you. Make sure to adjust the `DB_ROOT_PASSWORD` variable in your `.env` file before running the script. If you want to have more manual control over the process, you can follow the steps in the next section.

> **IMPORTANT** The script will create a backup of the database before doing any changes, however that means you need enough free space on your disk to create the backup. If you don't have enough free space, you should do the upgrade manually as described in the next section.

On your server, where you have the `docker-compose.yml` file, create a new file called `mysql-upgrade.sh` with the following content and make it executable: `chmod +x mysql-upgrade.sh`. Follow the steps in the script, it will guide you through the upgrade process and will automatically clean up any temporary files it creates.

```bash
#!/usr/bin/env bash
set -euo pipefail

# ── Helpers ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
step() { echo -e "\n${BLUE}▶ $*${NC}"; }
ok()   { echo -e "  ${GREEN}✓ $*${NC}"; }
warn() { echo -e "  ${YELLOW}! $*${NC}"; }
die()  { echo -e "  ${RED}✗ $*${NC}"; exit 1; }

# ── File names ────────────────────────────────────────────────────────────────
OVERRIDE="docker-compose.mysql-upgrade.yml"
TASK="mysql-upgrade-task.sh"
BACKUP_DIR="mysql-upgrade-backup-$(date +%Y%m%d-%H%M%S)"

trap 'step "Cleanup"; rm -f "$OVERRIDE" "$TASK"; ok "Temp files removed"' EXIT

# ── 1. Validate environment ───────────────────────────────────────────────────
step "Validating environment"

[[ -f ".env" ]] || die ".env not found in current directory"
set -a; source .env; set +a

missing=0
for var in PROJECT_NAME DB_ROOT_PASSWORD DB_USERNAME DB_PASSWORD DB_DATABASE; do
    if [[ -n "${!var:-}" ]]; then ok "$var is set"
    else echo -e "  ${RED}✗ $var is missing from .env${NC}"; missing=1
    fi
done
[[ $missing -eq 0 ]] || die "Fix missing variables in .env and retry"

# ── 2. Prepare backup directory ───────────────────────────────────────────────
step "Preparing backup directory"
mkdir -p "$BACKUP_DIR"
ok "Backup will be saved to: ./${BACKUP_DIR}/backup.sql"

# ── 3. Write the in-container upgrade script ──────────────────────────────────
step "Writing upgrade task"
cat > "$TASK" << 'TASK_CONTENT'
#!/bin/bash
set -e

SOCKET="/var/run/mysqld/mysqld.sock"

echo "→ Backing up all databases..."
mysqldump -S "$SOCKET" -u root --all-databases > /backup/backup.sql \
  && echo "  Backup OK" \
  || { echo "  Backup FAILED — aborting without touching auth tables."; exit 1; }

echo "→ Migrating auth plugins..."
mysql -S "$SOCKET" -u root << EOF
FLUSH PRIVILEGES;

ALTER USER 'root'@'localhost'    IDENTIFIED WITH caching_sha2_password BY '${DB_ROOT_PASSWORD}';
ALTER USER 'root'@'%'            IDENTIFIED WITH caching_sha2_password BY '${DB_ROOT_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'%'  IDENTIFIED WITH caching_sha2_password BY '${DB_PASSWORD}';

FLUSH PRIVILEGES;
EOF

echo "→ Verifying result..."
mysql -S "$SOCKET" -u root -p"${DB_ROOT_PASSWORD}" \
  -e "SELECT user, host, plugin FROM mysql.user WHERE user NOT LIKE 'mysql.%';"

echo "→ All done."
TASK_CONTENT
chmod +x "$TASK"
ok "Task script written"

# ── 4. Write the Compose override ─────────────────────────────────────────────
step "Writing Compose override"
cat > "$OVERRIDE" << COMPOSE_OVERRIDE
services:
  mysql:
    restart: "no"
    command:
      - --skip-grant-tables
      - --max_connections=2000
    volumes:
      - mysql_socket:/var/run/mysqld

  mysql-upgrade:
    image: mysql:8.4
    restart: "no"
    depends_on:
      mysql:
        condition: service_healthy
    env_file: .env
    volumes:
      - ./${TASK}:/upgrade.sh
      - ./${BACKUP_DIR}:/backup
      - mysql_socket:/var/run/mysqld
    command: ["bash", "/upgrade.sh"]

volumes:
  mysql_socket:
COMPOSE_OVERRIDE
ok "Override written"

# ── 5. Stop MySQL ─────────────────────────────────────────────────────────────
step "Stopping MySQL"
docker compose stop mysql
ok "MySQL stopped"

# ── 6. Run the upgrade ────────────────────────────────────────────────────────
step "Starting upgrade procedure"
warn "MySQL is running with --skip-grant-tables — do not interrupt!"
echo ""

docker compose \
  -f docker-compose.yml \
  -f "$OVERRIDE" \
  up mysql mysql-upgrade \
  --abort-on-container-exit \
  --exit-code-from mysql-upgrade

# ── 7. Summary ────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}======================================================"
echo    "  Migration complete!"
echo -e "======================================================${NC}"
echo ""
ok "Backup saved to: ./${BACKUP_DIR}/backup.sql"
warn "Next steps:"
echo "  1.  docker compose up -d"
echo "  2.  Confirm your app connects properly"
echo "  3.  Once satisfied: rm -r ${BACKUP_DIR}"
```

#### Upgrading your data manually

On your server, where you have the `docker-compose.yml` file, follow these steps:

1. Create a new file called `docker-compose.mysql-auth-plugin-upgrade.yml` and paste the following contents:
    ```yaml
    services:
      mysql:
        restart: "no"
        command:
          - --skip-grant-tables
          - --max_connections=2000
        volumes:
          - mysql_socket:/var/run/mysqld
    
      mysql-upgrade:
        image: mysql:8.4
        restart: "no"
        depends_on:
          mysql:
            condition: service_healthy
        env_file: .env
        volumes:
          - ./mysql-upgrade-backup/:/backup
          - ./mysql-auth-plugin-update.sh:/upgrade.sh
          - mysql_socket:/var/run/mysqld
        command: ["bash", "/upgrade.sh"]
    
    volumes:
      mysql_socket:
    ```
2. Create a new file called `mysql-auth-plugin-update.sh` and paste the following contents:
    ```bash
    #!/bin/bash
    set -e

    SOCKET="/var/run/mysqld/mysqld.sock"
   
    echo "Backing up all databases..."
    
    mysqldump -S "$SOCKET" -u root --all-databases > /backup/backup.sql \
      && echo "Backup OK" \
      || { echo "Backup FAILED — aborting without touching auth tables."; exit 1; }
   
    echo "Migrating auth plugins..."
    mysql -S "$SOCKET" -u root << EOF
    FLUSH PRIVILEGES;
    
    ALTER USER 'root'@'localhost'
    IDENTIFIED WITH caching_sha2_password BY '${DB_ROOT_PASSWORD}';
    
    ALTER USER 'root'@'%'
    IDENTIFIED WITH caching_sha2_password BY '${DB_ROOT_PASSWORD}';
    
    ALTER USER '${DB_USERNAME}'@'%'
    IDENTIFIED WITH caching_sha2_password BY '${DB_PASSWORD}';
    
    FLUSH PRIVILEGES;
    EOF
   
    echo "Verifying result..."
    mysql -S "$SOCKET" -u root -p"${DB_ROOT_PASSWORD}" \
    -e "SELECT user, host, plugin FROM mysql.user WHERE user NOT LIKE 'mysql.%';"

    echo "Migration complete."
    ```
3. Make the script executable: `chmod +x mysql-auth-plugin-update.sh`
4. Stop the MySQL container: `docker compose stop mysql`
5. Run the upgrade procedure: `docker compose -f docker-compose.yml -f docker-compose.mysql-auth-plugin-upgrade.yml up mysql mysql-upgrade --abort-on-container-exit --exit-code-from mysql-upgrade`
6. Once the upgrade is complete, you can remove the temporary files: `rm docker-compose.mysql-auth-plugin-upgrade.yml mysql-auth-plugin-update.sh`
7. Start the environment: `docker compose up -d`
8. Confirm your app connects properly and everything works as expected.
9. Once satisfied, you can delete the backup file created during the upgrade.

### `migrator` service: improved startup command

The migrator's `command` block now explicitly creates all required storage subdirectories and uses `gosu` instead of
`su` to switch to the `www-data` user. The `db:seed` step has also been removed from the migrator.

Update the `migrator` command in your `docker-compose.yml` to:

```yaml
command: >
    sh -c "
        mkdir -p /var/www/html/storage &&
        mkdir -p /var/www/html/storage/framework &&
        mkdir -p /var/www/html/storage/framework/cache &&
        mkdir -p /var/www/html/storage/framework/sessions &&
        mkdir -p /var/www/html/storage/framework/testing &&
        mkdir -p /var/www/html/storage/framework/views &&
        chmod -R 777 /var/www/html/storage &&
        gosu www-data php artisan migrate --force
    "
```

Also mount `./storage/logs` in the `migrator`, `queue`, and `reverb` services — the volume was previously missing:

```yaml
volumes:
    - ./storage/logs:/var/www/html/storage/logs
```

### New health check endpoint

The application now exposes a `/health` endpoint. The `app` container includes a Docker `HEALTHCHECK` directive that
polls this endpoint. No action is required unless you have external monitoring or load-balancer probes configured — in
that case update them to use `GET /health`.

See the new [Health Check documentation](_documentation/5-Deployment/Health-Check.md) for full details.

### Summary checklist

- [ ] Remove `nginx` service from `docker-compose.yml`
- [ ] Delete / stop tracking `nginx.default.conf`
- [ ] Remove `php_socket` named volume from `docker-compose.yml`
- [ ] Mount `./certs:/container/custom/certs` in the `app` service
- [ ] Add port mappings `80:80` and `443:443` to the `app` service
- [ ] Replace `APP_URL` with `APP_HOST` + `APP_PROTOCOL` in `.env`
- [ ] Add `DB_ROOT_PASSWORD` above `DB_PASSWORD` in `.env` (required by the MySQL image)
- [ ] Add `REVERB_HOST=reverb` to `.env`
- [ ] Replace `command:` with `PHP_WORKER_COMMAND` env var in `queue` and `reverb` services
- [ ] Update `migrator` command to use `gosu` and create storage subdirectories
- [ ] Mount `./storage/logs` in `migrator`, `queue`, and `reverb` services
