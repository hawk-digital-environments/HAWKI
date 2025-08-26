# Markdown version of the official Docker cheatsheet <!-- omit from toc -->
> The official PDF cheatsheet can be found [here](https://docs.docker.com/get-started/docker_cheatsheet.pdf).

This is the Markdown version of the official Docker cheatsheet, optimized for copy and paste! All rights belong to Docker.

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Installation](#installation)
- [General Commands](#general-commands)
- [Images Commands](#images-commands)
- [Containers Commands](#containers-commands)
- [Docker Hub Commands](#docker-hub-commands)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

# Installation
- Docker Desktop is the official and easiest way to install a bundle of Docker Engine and other useful components: https://docs.docker.com/desktop/install
- Docker Engine can be installed separate if you are using a VM without a GUI: https://docs.docker.com/engine/install
- Docker Compose plugin can be installed separately after Docker Engine and Docker CLI: https://docs.docker.com/compose/install
- VS Code Docker extension for a GUI to work with Docker images, containers and more: https://code.visualstudio.com/docs/containers/overview
- General documentation: https://docs.docker.com/

# General Commands
- Start the docker daemon
```bash
docker -d
```
- Get help with Docker. Can also use –help on all subcommands
```bash
docker --help
```
- Display system-wide information
```bash
docker info
```

# Images Commands
Docker images are a lightweight, standalone, executable package of software that includes everything needed to run an application: code, runtime, system tools, system libraries and settings.

- Build an Image from a Dockerfile
```bash
docker build -t <image_name>
```
- Build an Image from a Dockerfile without the cache
```bash
docker build -t <image_name> . --no-cache
```
- List local images
```bash
docker images
```
- Delete an Image
```bash
docker rmi <image_name>
```
- Remove all unused images
```bash
docker image prune
```

# Containers Commands
A container is a runtime instance of a docker image. A container will always run the same, regardless of the infrastructure. Containers isolate software from its environment and ensure that it works uniformly despite differences for instance between development and staging.

- Create and run a container from an image, with a custom name:
```bash
docker run --name <container_name> <image_name>
```
- Run a container with and publish a container’s port(s) to the host.
```bash
docker run -p <host_port>:<container_port> <image_name>
```
- Run a container in the background (detached mode)
```bash
docker run -d <image_name>
```
- Start or stop an existing container:
```bash
docker start|stop <container_name> (or <container-id>)
```
- Remove a stopped container:
```bash
docker rm <container_name>
```
- Open a shell inside a running container:
```bash
docker exec -it <container_name> sh
```
- Fetch and follow the logs of a container:
```bash
docker logs -f <container_name>
```
- To inspect a running container:
```bash
docker inspect <container_name> (or <container_id>)
```
- To list currently running containers:
```bash
docker ps
```
- List all docker containers (running and stopped):
```bash
docker ps --all
```
- View resource usage stats
```bash
docker container stats
```
# Docker Hub Commands
Docker Hub is a service provided by Docker for finding and sharing container images with your team. Learn more and find images at https://hub.docker.com/
- Login into Docker Hub
```bash
docker login -u <username>
```
- Publish an image to Docker Hub
```bash
docker push <username>/<image_name>
```
- Search Hub for an image
```bash
docker search <image_name>
```
- Pull an image from a Docker Hub
```bash
docker pull <image_name>
```
