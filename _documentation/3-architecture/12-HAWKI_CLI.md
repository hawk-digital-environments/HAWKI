---
sidebar_position: 12
---


# HAWKI CLI

## Overview

The HAWKI Command Line Interface (CLI) provides a set of powerful commands for managing and interacting with your HAWKI installation. This tool simplifies common administrative tasks, system configuration, and maintenance operations through an intuitive command line interface.

## Basic Usage

HAWKI CLI commands can be executed from the project root directory using:

```bash
php hawki [command] [options]
```

For help with available commands:

```bash
php hawki help
```

## Available Commands

### System Setup and Configuration

| Command | Description | Options |
|---------|-------------|---------|
| `check` | Check required dependencies | None |
| `init`, `initialize` | Initialize the project | `-all`: Continue to setup process |
| `setup` | Configure environment variables | `-g`: General settings  |  `-db`: Database settings  |  `-auth`: Authentication settings  |  `-reverb`: Reverb settings |
| `setup-models` | Configure AI model providers | None |
| `clear-cache` | Clear all Laravel caches | None |
| `migrate` | Run database migrations | `--fresh`: Reset and recreate all tables |

### User and Token Management

| Command | Description | Options |
|---------|-------------|---------|
| `token` | Create or revoke API tokens for a user | `--revoke`: Revoke a token instead of creating one |
| `remove-user` | Remove a user from the system | None |

### Application Management

| Command | Description | Options |
|---------|-------------|---------|
| `run` | Run development or build processes | `-dev`: Start development servers  |  `-build`: Build the project |
| `stop` | Stop all running processes | None |
| `help` | Show help message with available commands | None |

## Command Details

### Initialization and Setup

#### `check`

Checks if all required dependencies for HAWKI are installed and properly configured on your system.

```bash
php hawki check
```

The command verifies:
- PHP version (8.1+)
- Composer 
- Node.js and npm
- Required PHP extensions (mbstring, xml, pdo, curl, zip, json, fileinfo, openssl)

For missing dependencies, installation instructions are provided.

#### `init` or `initialize`

Initializes the HAWKI project by creating necessary configuration files from templates and running essential setup commands.

```bash
php hawki init
```

With the `-all` flag, it will continue through the complete setup process:

```bash
php hawki init -all
```

This command:
- Creates `.env` file from `.env.example`
- Sets up required configuration files
- Installs Composer dependencies
- Installs npm packages
- Creates storage symlinks
- Generates application keys and security salts

#### `setup`

Configures environment variables for different aspects of the HAWKI system.

```bash
# Configure all settings interactively
php hawki setup

# Configure only specific settings
php hawki setup -g    # General settings
php hawki setup -db   # Database settings
php hawki setup -auth # Authentication settings
php hawki setup -reverb # Reverb settings
```

This interactive command prompts for configuration values with sensible defaults.

#### `setup-models`

Configures AI model providers by setting up API keys and selecting default models.

```bash
php hawki setup-models
```

The command:
- Activates or deactivates AI providers
- Sets API keys for each active provider
- Configures the default model
- Sets models for system tasks (title generation, prompt improvement, etc.)

#### `clear-cache`

Clears all Laravel caches to ensure the application is running with fresh configurations.

```bash
php hawki clear-cache
```

This command clears:
- Configuration cache
- Application cache
- View cache
- Route cache
- Event cache
- Compiled files

#### `migrate`

Runs database migrations to create or update the database schema.

```bash
# Run pending migrations
php hawki migrate

# Reset database and recreate tables
php hawki migrate --fresh
```

The `--fresh` option will delete all tables and recreate them, resulting in data loss.

### User and Token Management

#### `token`

Creates or revokes API tokens for users, allowing them to access the HAWKI API.

```bash
# Create a new token
php hawki token

# Revoke an existing token
php hawki token --revoke
```

This interactive command:
1. Asks how to identify the user (username, email, or ID)
2. Requests the identification value
3. For token creation: Prompts for a token name (max 16 characters)
4. For token revocation: Lists available tokens and prompts for the token ID to revoke

#### `remove-user`

Removes a user from the HAWKI system, including all associated data.

```bash
php hawki remove-user
```

This command:
1. Asks for confirmation before proceeding
2. Requests how to identify the user (username, email, or ID)
3. Removes the user and associated data after confirmation

### Application Management

#### `run`

Runs development servers or builds the project.

```bash
# Start development servers
php hawki run -dev

# Build the project for production
php hawki run -build
```

The `-dev` option starts:
- PHP development server
- npm development server with hot-reloading
- Reverb websocket server
- Queue workers for various tasks

The `-build` option:
- Installs Composer dependencies
- Updates outdated packages
- Builds frontend assets for production
- Clears caches

#### `stop`

Stops all running HAWKI processes.

```bash
php hawki stop
```

This command finds and terminates:
- PHP Artisan processes
- Node.js/npm processes
- Queue workers
- Reverb server

## Best Practices

### System Administration

- Always run `check` before installation to ensure all dependencies are met
- Use `init -all` for a guided setup of a new installation
- Run `clear-cache` after configuration changes
- Create regular database backups before running `migrate --fresh`

### User Management

- Review the user's activities before using `remove-user`
- Establish a naming convention for API tokens (e.g., "user-service-purpose")
- Regularly audit tokens using `token --revoke` to remove unused tokens
- Consider token rotation policies for enhanced security

### Development

- Use `run -dev` during development for hot-reloading
- Always run `run -build` before deploying to production
- Ensure `stop` is executed when switching between projects to free resources

## Troubleshooting

### Common Issues

**Missing Dependencies**
```
✗ PHP extension: pdo (missing)
```
Run `php hawki check` to get installation instructions for missing dependencies.

**Failed Commands**
```
Error: Unable to connect to database
```
Check database configuration with `php hawki setup -db` and ensure the database server is running.

**PHP Artisan Errors**
If direct Artisan commands fail but HAWKI commands work, there might be a path or environment issue. Try using the full PHP path:
```bash
/usr/bin/php artisan [command]
```

## Advanced Usage

### Scripting and Automation

HAWKI commands can be used in shell scripts for automation:

```bash
#!/bin/bash
# Example: Automated deployment update

# Navigate to project directory
cd /path/to/hawki

# Pull latest code
git pull

# Build and deploy
php hawki run -build
php hawki clear-cache
php hawki migrate

echo "Deployment complete!"
```

### Custom Commands

The HAWKI CLI is built on Laravel's Artisan command system. Additional commands can be created in the `app/Console/Commands` directory.

## Conclusion

The HAWKI Command Line Interface provides a comprehensive set of tools for managing your HAWKI installation. From initial setup to ongoing maintenance, these commands simplify administration and development tasks, allowing you to focus on providing excellent AI chat services to your users.

For more detailed information on specific aspects of HAWKI, refer to the other documentation files in this directory.