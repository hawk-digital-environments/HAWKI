---
sidebar_position: 3
---

# Docker Deployment

Instead of manually setting up PHP and Apache [as described in "Apache Deployment"](1-Apache%20Server.md), you can use
Docker to deploy HAWKI. Our official
image [digitalenvironments/hawki](https://hub.docker.com/r/digitalenvironments/hawki) is
available on Docker Hub.

## Docker Compose

For smaller setups you can use a simple Docker Compose file to run HAWKI.
In the `_docker_production` directory of this repo you can find a preconfigured setup how your production server
could look like. It features a built-in nginx web server (inside the `app` container), a mysql database, and a redis
cache.

**Please consider it a starting point you can/should adapt to your needs.**

A few things to consider:

* SSL Certificates - Place your SSL certificates in the `certs` directory (`cert.pem`, `key.pem`). They are mounted
  directly into the `app` container at `./certs:/container/custom/certs`. The container handles SSL termination
  internally — there is no separate nginx proxy container. If no certificates are provided, the container will
  generate self-signed certificates on startup (expect browser warnings).
* Environment Variables - There is already a minimal `.env` file available in the `_docker_production` directory,
  adjust it to fit your needs. Set `APP_HOST` to your domain and `APP_PROTOCOL` to `http` or `https`. Other
  variables (e.g. `APP_URL`, `DOCKER_PROJECT_HOST`) are derived from these two automatically.
  Note that some variables should be kept as is, because they are required for the docker-compose setup. But some MUST
  be adjusted for security (e.g. the passwords and encryption keys).
  You can extend the `.env` file with any variable you find in the `.env.example` file to adjust HAWKI to your needs;
  if not given the default value will be used.
* SQL Database - For ease of use the MySQL data is stored in a docker volume, for a more permanent setup you may
  adjust the `mysql_data:/var/lib/mysql` line so it points to a directory on your host. Or, if you already have a
  database server, you can point the container to it and remove the mysql service entirely.
* Authentication - To authenticate users you can use LDAP, OpenID Connect or SAML, adjust the `.env` file as
  described in the `Setup Authentication Methods` section of the [Apache Deployment](1-Apache%20Server.md) guide.
* Model configuration - You find a default `model_providers.php` file in the `_docker_production` directory, which
  will be mounted to the HAWKI container. Please adjust it as described in the `Adding API Keys` section of the
  [Apache Deployment](1-Apache%20Server.md) guide.

### What's in the box

The `docker-compose.yml` contains a setup of multiple services

* `app` - The HAWKI container. It runs both nginx (for serving the web application) and PHP-FPM internally. Ports
  80 and 443 are exposed directly from this container — there is no separate nginx proxy service.
* `queue` - The queue worker container, which runs in the background and processes background jobs (e.g. emails,
  message broadcasting). Scale this service to handle higher workloads.
* `reverb` - The reverb server container, which handles the real-time communication between the client and the server
  using Websockets. Feel free to scale up the number of reverb workers if you have a large number of users.
* `migrator` - A one-shot container that runs database migrations before the other services start. It ensures the
  database schema is up to date before the application becomes available.
* `mysql` - The MySQL container
* `redis` - The Redis container used for caching and reverb communication

### Health Check

The `app` container exposes a `/health` endpoint that reports the status of the database, cache, Redis, and storage.
It is used by Docker's built-in health check mechanism and can also be used by external monitoring tools or load
balancers. See the [Health Check documentation](6-Health-Check.md) for details.

### Deployment

Once you made the necessary adjustments on the files mentioned above copy the `_docker_production` directory to your
server and execute the following command: `chmod +x deploy.sh && ./deploy.sh`. This will automatically
bring up the containers and run the migrations.

## Building a custom container

Of course, you can completely customize the container if you want to. The `Dockerfile` in the root of the repository
provides the `app_prod` build target which builds a production ready HAWKI container. The production image is based on
[`neunerlei/php-nginx`](https://github.com/Neunerlei/docker-images/blob/main/docs/php-nginx.md), which bundles PHP-FPM
and nginx into a single container. Feel free to modify the `Dockerfile` to your needs or inherit your own image from
the `digitalenvironments/hawki` image.

Build the image: `docker build --target app_prod -t digitalenvironments/hawki:latest .`
Or by using: `bin/env docker:build:prod`

## Headsup and good to know!

We encountered an issue
when [disabling the "iptables"](https://docs.docker.com/engine/network/packet-filtering-firewalls/#prevent-docker-from-manipulating-iptables)
in the docker daemon.json file. This does (if not configured manually) break the communication between the containers
and the outside world.

## Update notes:

- **Updating to v2.1.0**: Please compare your current setup with the new `_docker_production` directory, as there are
  some changes needed to update to version 2.1.0.
  Especially the `.env` file has some new variables that need to be added. Also the model_providers.php file has a
  changed format and was moved to another location, the model_lists have been added and a new services were introduced
  to the docker-compose file (the scheduler and file-converter).
