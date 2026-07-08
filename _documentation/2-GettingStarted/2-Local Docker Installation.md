---
sidebar_position: 2
---

# Local Docker Installation

This guide will walk you through setting up a local Docker environment for developing and testing HAWKI on your local machine.
Please keep in mind, that this guide is intended for development and testing purposes only. For production,
please refer to the [production installation guide](../5-Deployment/3-Docker%20Deployment.md), as it is more secure and
robust.

The PHP(8.4) container is based on an alpine linux using FPM which can be found [here](https://github.com/Neunerlei/docker-php),
we are planning to deploy our own php base image built on a ubuntu distro in the future.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

Theoretically, it should work with any operating system that supports Docker and Docker Compose.
It also should work with [podman](https://podman.io/) and [podman-compose](https://docs.podman.io/en/latest/markdown/podman-compose.1.html)
as a drop-in replacement for Docker and Docker Compose, however this has not been tested; if you are experiencing issues, please let us know!

## Installation

You have two options to run HAWKI with docker; you can either set up everything manually or use our `bin/env` script to
control the environment for you. This guide will have some overlaps with
the [Local Installation Guide](1-Local%20Installation.md),
and will refer to it for some steps.

### Defining your API keys for the AI Models

This is necessary to configure the AI models that HAWKI uses, you MUST do this before starting the application.
Follow the "Configuration -> API KEYS" section of the [Local Installation Guide](1-Local%20Installation.md) to define
your API keys.

### The fast way: Using the `bin/env` script

1. **Clone the Git Repo:**

```
git clone -b main https://github.com/hawk-digital-environments/HAWKI.git
```

2. **Navigate to the project folder:**

```
cd HAWKI
```

3. **Run the `bin/env` script:**

```
bin/env up
```

OR (if you want to follow the output of the containers)

```
bin/env up -f
```

**HINT:** bin/env SHOULD be executable by default. If it is not, you can make it executable by running `chmod +x bin/env`.

If you not already have the `.env` file, the script will ask you to create one. You can use the default values by pressing enter (twice).
The script will also automatically create a test user for you, if you have not already done so.
You will be able to log in with the username `tester` and the password `tester`.

4. **Start the workers:**

(If you opted for the `-f` flag in the previous step, you will need to open a new terminal window for this step).
This process MUST be running while you are using the application!

```
bin/env dev
```

5. **Access the application:**

(Do this either in the original terminal window (if not using the -f) or in a new one).
This command will open the application in your default browser.

```
bin/env open
```

Well done!

#### A few words on the `bin/env` script.

The script is build using node.js and is located in the `bin` folder.
For the most part it acts as a convenience wrapper around `docker-compose` and `docker` commands, but it also has some additional features.
To see all available commands, run `bin/env` without any arguments to see a detailed help.

A few commands that might be useful:

- `bin/env up` will start the application. (Supports all docker compose flags + "-f" to follow the output of the containers)
- `bin/env down` will stop the application and remove all containers. (Supports all docker compose flags)
- `bin/env restart` will restart the application.
- `bin/env open` will open the application in your default browser.
- `bin/env dev` will start the worker and websocket listeners.
- `bin/env ssh` will open a shell into a container (If no parameter is given, it will open the shell of the php container); can use any "docker-compose" service name to shell into that container.
- `bin/env logs` will show the logs of the containers. (Supports all docker compose flags)
- `bin/env composer` executes the composer command in the php container. (Works like a normal composer command)
- `bin/env npm` executes the npm command in the node container. (Works like a normal npm command)
- `bin/env artisan` executes the artisan command in the php container. (Works like a normal artisan command)

### Manual Installation

1. **Clone the Git Repo:**

```
git clone -b main https://github.com/hawk-digital-environments/HAWKI.git
```

2. **Navigate to the project folder:**

```
cd HAWKI
```

3. **Create the `.env` file:**

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Adjust the `.env` file to your needs. You can use the default values for development.

To configure the file for the docker-compose.yml file you can use the following values:

```dotenv
APP_URL=http://localhost
AUTHENTICATION_METHOD=LDAP# or OPENID or SHIBBOLETH
TEST_USER_LOGIN=true
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=db
DB_USERNAME=user
DB_PASSWORD=password
REVERB_APP_ID=hawki
REVERB_APP_KEY=hawki
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=80
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH="/usr/bin/mhsendmail --from=test@example.org --smtp-addr=mailhog:1025 -t"
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_USERNAME=default
REDIS_PASSWORD=password
```

You also need to fill some additional values, but you must generate the values yourself.

```dotenv
APP_KEY=RANDOM_STRING(32)
REVERB_APP_SECRET=RANDOM_STRING(32)
USERDATA_ENCRYPTION_SALT=BASE_64(RANDOM_STRING(32))
INVITATION_SALT=BASE_64(RANDOM_STRING(32))
AI_CRYPTO_SALT=BASE_64(RANDOM_STRING(32))
PASSKEY_SALT=BASE_64(RANDOM_STRING(32))
BACKUP_SALT=BASE_64(RANDOM_STRING(32))
```

All other values are optional and can be left as they are OR adjusted to your needs.

4. **Install the test user:**

Follow the "Configuration -> Authentication" section of the [Local Installation Guide](1-Local%20Installation.md) to
create
the test user file.

5. **Start the application:**

The "-d" is optional and will start the containers in the background. If you want to follow the output of the containers, you can omit the "-d".

```bash
docker-compose up -d
```

6. **Start the queue worker:**

This processes keeps running and will block your terminal. You can open a new terminal window to run the next command.

```bash
docker-compose exec php php artisan queue:work --queue=default,mails,message_broadcast
```

7. **Start the reverb websocket server:**

This processes keeps running and will block your terminal. You can open a new terminal window to run the next command.

```bash
docker-compose exec php php artisan reverb:start
```

8. **Access the application:**

Open your browser and navigate to [http://localhost](http://localhost).

## Integrating Tools with your IDE

### PHP CS Fixer

CS fixer is a tool that automatically formats your code according to a set of rules. We use it to ensure that our codebase is consistent and follows the PSR-12 coding standard.

You can manually run the fixer with the following command, which will boot up the php container if needed and execute the fixer command inside it:

```bash
bin/env style php
```

However many IDEs also support integrating the fixer directly into the editor, so you can see formatting issues in real-time and automatically fix them on save. When you are running in a docker container, you need to configure the IDE to use the fixer executable inside the container.

Always bring up the container with `bin/env up` before trying to use the fixer in your IDE, otherwise the IDE won't be able to find the executable and will throw an error.

**PHP Storm:**

1. Go to `Settings` > `Languages & Frameworks` > `PHP` > `Quality Tools` > `PHP CS Fixer`.
2. Next to **"Configuration: By default project interpreter"** click the **`...`** button.
3. In the new modal, click on **"By default project interpreter"**, then in the right panel click **`...`** next to **"CLI Interpreter"**.
4. In the modal that opens, click the **`+`** button in the top-left and select **"From Docker, Vagrant, VM..."**.
5. A new dialog opens — configure it as follows:
    - **Server type:** `Docker Compose`
    - **Configuration files:** `./docker-compose.yml; ./docker-compose.override.yml`
    - **Service:** `app`
    - **Environment variables:** *(leave empty)*
    - **PHP interpreter path:** `php`

   Click **OK**.
6. You are now back in the CLI Interpreters modal. Fill in the right panel:
    - **Name:** `app` (or any name you like)
    - Under **General**, click the **refresh** icon next to **"PHP executable"** — this should detect and display the PHP version.

   Click **OK**.
7. Back in the second modal, the **CLI Interpreter** should now automatically be set to `app` (or the name you chose) and PhpStorm should apply the path mappings for you.
    - **PHP CS Fixer path:** `/var/www/html/vendor/bin/php-cs-fixer`

   Click **OK**.
8. Back in the main **Settings** modal, under **Options**:
    - **Ruleset:** `Custom`
    - **Path:** `$PATH_TO_YOUR_PROJECT/HAWKI`
9. In the **Settings tree** on the left, navigate to **"Quality Tools"**.
   In the right panel, set **"External Formatters"** to **"PHP CS Fixer"**.

   Click **OK**.

### Prettier

Prettier is a code formatter for JavaScript and TypeScript. We use it to ensure that our frontend codebase is consistent and follows a defined coding style.

You can manually run prettier with the following command, which will boot up the node container if needed and execute the prettier command inside it:

```bash
bin/env style js
```

However many IDEs also support integrating prettier directly into the editor, so you can see formatting issues in real-time and automatically fix them on save. The easiest way to do this is to boot up the container once with `bin/env up` which will install the node modules on your machine, which allows the IDE to find the prettier executable on your local file system. After that, you can configure the IDE to use the local prettier executable as you normally would, and it should work without any issues.

1. Go to `Settings` > `Languages & Frameworks` > `Javascript` > `Prettier`.
2. Check prettier package has auto-detected, should be something like myproject/node_modules/prettier
3. Update Run for Files to look like this: `{resources,public,.github,_docker-production,_documentation.build}/**/*.{js,ts,svelte,css,yml,yaml,json}`
4. Tick the **On Save button**, if you want your files formatting updated on file save
5. Click **OK**.

## Running tests

HAWKI uses PHPUnit for testing of the PHP code, which also runs inside the php container. You can run the tests with the following command, which will boot up the php container if needed and execute the tests inside it:

```bash
bin/env test unit
```

For static code analysis, we use PHPStan, which also runs inside the php container. You can run it with the following command:

```bash
bin/env test stan
```

Or if you want to run all tests and static analysis in one go, you can use the `test` command:

```bash
bin/env test php all
```

## Debugging

### PHP debug configuration
The default configuration is mostly configurable by env files.

The defaults are:
```
XDEBUG_MODE="${XDEBUG_MODE:-debug}"
XDEBUG_START_WITH_REQUEST="${XDEBUG_START_WITH_REQUEST:-yes}"
XDEBUG_CLIENT_HOST="${XDEBUG_CLIENT_HOST:-host.docker.internal}"
XDEBUG_CLIENT_PORT="${XDEBUG_CLIENT_PORT:-9003}"
XDEBUG_LOG="${XDEBUG_LOG:-/var/www/html/storage/logs/xdebug.log}"
XDEBUG_LOG_LEVEL="${XDEBUG_LOG_LEVEL:-7}"
```
Example override in .env: `XDEBUG_CLIENT_PORT=9000`

See `docker/app/php/php.dev.ini` for details.

### vscode
If hawki services have been started successfully e.g. via `bin/env up -f --build`,
a debugger can be connected to the app container, after installing your favorite vscode php debug extensions.
Example extensions:
    - [bmewburn.vscode-intelephense-client](https://github.com/bmewburn/vscode-intelephense)
    - [xdebug.php-debug](https://github.com/xdebug/vscode-php-debug)
`` 
Example for entry in `.vscode/launch.json`:
```json
    {
        "name": "Listen for Xdebug in development app service",
        "type": "php",
        "request": "launch",
        "port": 9003,
        "pathMappings": {
            "/var/www/html": "${workspaceFolder}" 
        }
    }
```
