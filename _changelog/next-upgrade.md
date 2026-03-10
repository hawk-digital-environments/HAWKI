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

| Variable       | Example                  | Description                                            |
|----------------|--------------------------|--------------------------------------------------------|
| `APP_HOST`     | `yourdomain.example.com` | The public hostname without protocol or trailing slash |
| `APP_PROTOCOL` | `https`                  | Either `http` or `https`                               |

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
- [ ] Add `REVERB_HOST=reverb` to `.env`
- [ ] Replace `command:` with `PHP_WORKER_COMMAND` env var in `queue` and `reverb` services
- [ ] Update `migrator` command to use `gosu` and create storage subdirectories
- [ ] Mount `./storage/logs` in `migrator`, `queue`, and `reverb` services
