# vX.Y.Z

### What's New

- Health check endpoint: Adds a dedicated `/health` endpoint to HAWKI. This endpoint allows you to see if HAWKI is running and healthy. Every 10 executions of the health check, a more detailed check is performed that also checks the database connection and other components. This is especially useful in docker deployments, where this endpoint can be used by orchestration tools to verify that the service is running correctly.

#### Docker

- Complete refactoring of the Docker setup to reduce complexity and improve maintainability. The docker image is now based on [php-nginx](https://github.com/Neunerlei/docker-images/blob/main/docs/php-nginx.md) and provides nginx out of the box. So we no longer need a separate nginx container. **IMPORTANT - take a look at the upgrade guide, please!**

### Quality of Life

- Allow `DB_BACKUP_INTERVAL` to be set to `never`, to disable automatic database backups. This is useful for users who want to manage their own backup strategy or do not want to use the built-in backup functionality.
- Adds a new `DB_BACKUP_INTERVAL_ARGS` environment variable, that works in tandem with `DB_BACKUP_INTERVAL`, to allow more fine-grained control over the database backup process. Read more in the [Dot Env documentation](../_documentation/3-architecture/10-dot%20Env.md)

### Bugfix

- Fixed LDAP authentication to gracefully handle attributes returned in lowercase by the LDAP server.

### Deprecation

- List of features or functionalities that have been deprecated in this version.
