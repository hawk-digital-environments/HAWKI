---
sidebar_position: 3
---

# Docker Deployment

Instead of manually setting up PHP and Apache [as described in "Apache Deployment"](Apache%20Deployment.md), you can use
Docker to deploy HAWKI. Our official
image [digitalenvironments/hawki](https://hub.docker.com/r/digitalenvironments/hawki) is
available on Docker Hub.

## Docker Compose

For smaller setups you can use a simple Docker Compose file to run HAWKI.
In the `_docker_production` directory of this repo you can find a preconfigured setup how your production server
could look like. It features an nginx proxy, a mysql database as well as a redis cache and a php-fpm server.

**Please consider it a starting point you can/should adapt to your needs.**

A few things to consider:

* SSL Certificates - Place your SSL certificates in the "certs" directory, (cert.pem, key.pem) which will be mounted to
  the nginx container. Keep in mind, that inside the `docker compose` network there is NO SSL encryption. The SSL
  connection terminates at the nginx layer.
* Environment Variables - There is already a minimal .env file available in the `_docker_production` directory, adjust
  it to fit your needs.
  Note that some variables should be kept as is, because they are required for the docker-compose setup. But some MUST
  be adjusted for security (e.g. the passwords and encryption keys).
  You can extend the .env file with any variable you find in the `.env.example` file to adjust HAWKI to your needs; if
  not given the default value will be used.
* SQL Database - For ease of use the MYSQL data is stored in a docker volume, for a more permanent setup you may adjust
  the `mysql_data:/var/lib/mysql` line, so it points to a directory on your host. Or, if you already have a database
  server, you can obviously point the container to it and remove the mysql service entirely.
* Nginx - The nginx server acts as a main entrypoint for the application, it is configured to listen on port 80 and 443,
  with automatic SSL redirection. Check the `nginx.default.conf` file for more details and adapt it to your needs.
* Authentication - To authenticate users you can use LDAP, OpenID Connect or SAML, adjust the `.env` file as described
  in the `Setup Authentication Methods` section of the [Apache Deployment](Apache%20Deployment.md) guide.
* Model configuration - You find a default `model_providers.php` file in the `_docker_production` directory, you
  which will be mounted to the HAWKI container. Please adjust it as described in the `Adding API Keys` section of the
  [Apache Deployment](Apache%20Deployment.md) guide.

### What's in the box

The `docker-compose.yml` contains a setup of multiple services

* `app` - The HAWKI container
* `queue` - The queue worker container, which runs in the background and restarts every 90 seconds
* `reverb` - The reverb server container, which handles the real-time communication between the client and the server
  using Websockets. Feel free to scale up the number of reverb workers if you have a large number of users.
* `mysql` - The MySQL container
* `redis` - The Redis container used for caching and reverb communication
* `nginx` - The nginx container, which handles the reverse proxy and SSL termination

### Deployment

Once you made the necessary adjustments on the files mentioned above copy the `_docker_production` directory to your
server and execute the following command: `chmod +x deploy.sh && ./deploy.sh`. This will automatically
bring up the containers and run the migrations.

## Building a custom container

Of course, you can completely customize the container if you want to. The `Dockerfile` in the root of the repository
provides the `app_prod` build target which builds a production ready HAWKI container. Feel free to modify it to your
needs or inherit your own image from the `digitalenvironments/hawki` image.

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
