# vX.Y.Z

### What's New

- Health check endpoint: Adds a dedicated `/health` endpoint to HAWKI. This endpoint allows you to see if HAWKI is running and healthy. Every 10 executions of the health check, a more detailed check is performed that also checks the database connection and other components. This is especially useful in docker deployments, where this endpoint can be used by orchestration tools to verify that the service is running correctly.

#### Docker

- Complete refactoring of the Docker setup to reduce complexity and improve maintainability. The docker image is now based on [php-nginx](https://github.com/Neunerlei/docker-images/blob/main/docs/php-nginx.md) and provides nginx out of the box. So we no longer need a separate nginx container. **IMPORTANT - take a look at the upgrade guide, please!**

### Quality of Life

- Improvements and enhancements that improve the user experience.

### Bugfix

- List of bugs that have been fixed in this version.

### Deprecation

- List of features or functionalities that have been deprecated in this version.
