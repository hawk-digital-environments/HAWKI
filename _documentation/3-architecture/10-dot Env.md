---
sidebar_position: 10
---

# dot Env

## HAWKI 2: Configuration environment variables

HAWKI 2 is built with the Laravel framework for PHP. This allows to configure the application by editing the *.php files in the config directory. However, by default these files are written in such a way that most settings can also be changed with environment variables. This is handy because changing the config files would mean to change the HAWKI 2 source code, since these files are managed in the HAWKI git repository and also contained in the installation packages. Environment variables, however, are set in the OS (or your hosting environment).

Additionally, Laravel reads the contents of a .env file in the project root directory to override the environment variables. This file is not contained in the HAWKI installation as it is meant for developers and administrators to set their local configuration values.

This file is a template for the .env file. It documents all relevant settings, their allowed values and their defaults. Simply copy this file (don't just rename it) to a new file called .env and change the values as needed. The pre-set values will provide a sensible configuration for a "localhost deployment" for developers. Commented out settings are not strictly needed and usually contain the default values found in the config/*.php files.


>**Important:** the .env example file includes several unused variables which are left in the file only for future development plans or as guidelines for community developers (such as AWS, Redis, Pusher and many more attributes). These variables can be ignored or removed if you prefer so.


## Global Application Settings

| Variable               | Default Value         | Description                                                                                                   |
|------------------------|-----------------------|---------------------------------------------------------------------------------------------------------------|
| PROJECT_NAME           | hawki2                | URL-safe name of the project (lowercase, no spaces, no special characters) used for naming docker containers  |
| APP_NAME               | HAWKI2                | Application name, can be anything you like                                                                    |
| APP_ENV                | local                 | Deployment type: "local", "staging" or "production"                                                           |
| APP_URL                | http://127.0.0.1:8000 | Public URL to access the web interface                                                                        |
| APP_DEBUG              | true                  | Enable debug output: "true" or "false"                                                                        |
| APP_TIMEZONE           | CET                   | Timezone of the web server                                                                                    |
| APP_LOCALE             | de_DE                 | Default language of the user interface (de_DE, en_US)                                                         |
| APP_FALLBACK_LOCALE    | en_US                 | Fallback language (for any missing translations)                                                              |
| APP_FAKER_LOCALE       | de_DE                 | Laravel faker locale for testing data generation                                                              |
| APP_KEY                |                       | Encryption key: base64 encoded 32-byte key (generate with `php artisan key:generate`)                         |
| APP_PREVIOUS_KEYS      |                       | Comma separated list of previously used encryption keys                                                       |
| APP_MAINTENANCE_DRIVER | cache                 | Maintenance mode driver: "cache" or "file" - "cache" allows setting maintenance mode across multiple machines |
| APP_MAINTENANCE_STORE  | database              | Maintenance mode storage: "database" or "file"                                                                |
| AI_MENTION_HANDLE      | hawki                 | Handle to mention AI in group chats (without @ symbol)                                                        |





## Database Server

HAWKI uses a database to save chats and other data. For this a relation SQL database like MariaDB (MySQL) or Postgres should be used in production. For local development SQLite provides a zero-config default solution (but note that SQLite is single-user only and stores all data in a single file).

>***IMPORTANT:*** When using a database other than SQLite set DB_DATABASE to a sensible value. Because the default value in config/database.php is "laravel" which is less clear and could at least in theory already be in use by other applications.

| Variable           | Default Value      | Description                                                                                     |
|--------------------|--------------------|-------------------------------------------------------------------------------------------------|
| DB_CONNECTION      | mysql              | Database server type: "mysql", "sqlite", "mariadb", "pgsql", "sqlsrv" (see config/database.php) |
| DB_BACKUP_INTERVAL | daily              | Automatic database backup interval: "daily", "weekly", etc.                                     |
| DB_URL             |                    | Database connection URL (instead of host and port)                                              |
| DB_HOST            | localhost          | Database server host name                                                                       |
| DB_PORT            | 3306               | Database server port number                                                                     |
| DB_SOCKET          |                    | Unix domain socket instead of URL, host and port (MySQL and MariaDB only)                       |
| DB_DATABASE        | HAWKI2             | Database name (please change for your installation!)                                            |
| DB_USERNAME        | root               | Username to access the database server                                                          |
| DB_PASSWORD        |                    | Password to access the database server                                                          |
| DB_CHARSET         | utf8mb4            | Character encoding of the database                                                              |
| DB_COLLATION       | utf8mb4_unicode_ci | Database collation (MySQL and MariaDB only)                                                     |
| MYSQL_ATTR_SSL_CA  |                    | SSL Certificate Authority file for MySQL SSL connections                                        |






## HAWKI Profile Configuration

Configuration for the HAWKI AI assistant profile and system user.

>**Note:** These migration attributes cannot be changed after running the database migration. If you want to change the HAWKI_AVATAR, place the file in `/public/img` folder and update the name here before migration.

| Variable       | Default Value   | Description                                                |
|----------------|-----------------|------------------------------------------------------------|
| HAWKI_NAME     | HAWKI           | Display name for the HAWKI AI assistant                    |
| HAWKI_USERNAME | HAWKI           | Username for the HAWKI AI assistant                        |
| HAWKI_AVATAR   | hawkiAvatar.jpg | Avatar image filename (must be in `/public/img` directory) |

## AI Connection

Configuration for AI provider API keys and endpoints. Add your AI provider API keys here to enable AI functionality.

>**Note:** For complete provider configuration and advanced model settings, refer to the HAWKI Documentation and the `config/model_providers.php` file.

| Variable            | Default Value | Description                                           |
|---------------------|---------------|-------------------------------------------------------|
| OPENAI_API_KEY      |               | OpenAI API key for accessing GPT models               |
| GWDG_API_KEY        |               | GWDG API key for accessing academic cloud AI services |
| GOOGLE_API_KEY      |               | Google AI API key for accessing Gemini models         |
| OPEN_WEB_UI_API_KEY |               | OpenWebUI API key for local AI model access           |

## File Converter Configuration

Configuration for document conversion services. Choose between HAWIK's built-in converter or GWDG's Docling service.

>**Note:** To use GWDG Docling converter, you'll need a valid GWDG_API_KEY.

| Variable                     | Default Value                                         | Description                                           |
|------------------------------|-------------------------------------------------------|-------------------------------------------------------|
| FILE_CONVERTER               | hawki_converter                                       | Converter to use: "hawki_converter" or "gwdg_docling" |
| HAWKI_FILE_CONVERTER_API_URL | 127.0.0.1:8001/extract                                | URL to the hosted HAWKI converter service             |
| HAWKI_FILE_CONVERTER_API_KEY | 123456                                                | API key for the HAWKI converter service               |
| GWDG_FILE_CONVERTER_API_URL  | https://chat-ai.academiccloud.de/v1/documents/convert | URL to the GWDG Docling converter service             |

## Filesystem Storage

Uploaded media files are typically stored on disk and served from an asset web server. However, to simplify the setup the uploaded files will be served by PHP by default, though this is not optimal for performance. Alternatively, Amazon S3, Nextcloud, or SFTP can be used.

>**NOTE:** If you want to serve media files with your web server, choose "public" for FILESYSTEM_DISK. In this case make the server serve the files from the "storage/app/public" directory. The HTTP address must be your APP_URL followed by "/storage".

| Variable                  | Default Value      | Description                                                        |
|---------------------------|--------------------|--------------------------------------------------------------------|
| FILESYSTEM_DISK           | local              | Primary storage type: "local", "public", "s3", "nextcloud", "sftp" |
| STORAGE_DISK              | local_file_storage | Storage disk for file attachments                                  |
| AVATAR_STORAGE            | public             | Storage disk for user avatars                                      |
| REMOVE_FILES_AFTER_MONTHS | 6                  | Automatic file cleanup after X months                              |

### S3 Configuration
| Variable          | Default Value | Description            |
|-------------------|---------------|------------------------|
| S3_ACCESS_KEY     |               | S3 access key          |
| S3_SECRET_KEY     |               | S3 secret key          |
| S3_REGION         |               | S3 region              |
| S3_ENDPOINT       |               | S3 endpoint URL        |
| S3_DEFAULT_BUCKET |               | Default S3 bucket name |

### Nextcloud Configuration
| Variable            | Default Value | Description                                |
|---------------------|---------------|--------------------------------------------|
| NEXTCLOUD_BASE_URL  |               | Base URL of your Nextcloud instance        |
| NEXTCLOUD_BASE_PATH |               | Base path within Nextcloud for HAWKI files |
| NEXTCLOUD_USERNAME  |               | Nextcloud username                         |
| NEXTCLOUD_PASSWORD  |               | Nextcloud password or app token            |

### SFTP Configuration
| Variable       | Default Value | Description              |
|----------------|---------------|--------------------------|
| SFTP_HOST      |               | SFTP server hostname     |
| SFTP_PORT      | 22            | SFTP server port         |
| SFTP_USERNAME  |               | SFTP username            |
| SFTP_PASSWORD  |               | SFTP password            |
| SFTP_BASE_PATH |               | Base path on SFTP server |



## Session Configuration


These are essential Laravel default variables for session management, and they must be present and active to ensure proper session handling within the application.


| Variable                | Default Value | Description                                                                                                                                                                                           |
|-------------------------|---------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| SESSION_DRIVER          | database      | Specifies the session "driver" or handler used to store session data. Common choices include "file", "cookie", "database", etc. Typically, "database" is used if sessions are stored in the database. |
| SESSION_LIFETIME        | 120           | The session lifetime in minutes. It determines how long a session remains active before it expires.                                                                                                   |
| SESSION_ENCRYPT         | false         | Indicates whether session data should be encrypted. Accepts "true" or "false". When set to "true", it adds an extra layer of security by encrypting session data.                                     |
| SESSION_PATH            | /             | Defines the path for which the session cookie is available. The default value is "/".                                                                                                                 |
| SESSION_DOMAIN          | null          | Specifies the domain that the session cookie is available to. Use "null" to default to the current domain.                                                                                            |
| SESSION_EXPIRE_ON_CLOSE | true          | Defines whether the session should expire when the browser is closed. Set to "true" to expire sessions on browser close, enhancing session security.                                                  |




## Event Broadcasting


Laravel contains an internal mechanism to broadcast events amongst servers. Normally you shouldn't need to change the settings here. Just use the values below and only change them if you really know what you are doing.

| Variable             | Description                      |
|----------------------|----------------------------------|
| BROADCAST_CONNECTION | Broadcasting mechanism: "reverb" |
| BROADCAST_DRIVER     | Broadcasting mechanism: "reverb" |


For websocket connectivity Laravel provides a special server called Reverb. This must be set up for HAWKI since many features rely on real-time communication via web sockets.

| Variable                    | Default Value         | Description                                                                 |
|-----------------------------|-----------------------|-----------------------------------------------------------------------------|
| REVERB_HOST                 | 127.0.0.1 / hawki.com | Hostname of the Reverb server (set to your top-level domain for production) |
| REVERB_PORT                 | 8080 / 443            | Port number of the Reverb server (set to 443 for production)                |
| REVERB_SERVER_HOST          | 0.0.0.0               | Hostname of the Reverb server (set to 0.0.0.0 for production)               |
| REVERB_SERVER_PORT          | 8080                  | Port number of the Reverb server (set to 8080 for production)               |
| REVERB_SCHEME               | http/https            | Connection protocol for the Reverb server: "http" or "https"                |
| REVERB_APP_ID               | hawki                 | Reverb application Id, can be anything you like?                            |
| REVERB_APP_SECRET           | 123456789             | Password to access the Reverb server???                                     |
| REVERB_APP_KEY              | 123456789             | Secret key to access the Reverb server ???                                  |
| REVERB_APP_PING_INTERVAL    | 60                    | Ping interval in seconds ???                                                |
| REVERB_APP_MAX_MESSAGE_SIZE | 10000                 | Maximum message size in bytes ???                                           |
| REVERB_SCALING_ENABLED      | false                 | "true" or "false"                                                           |
| REVERB_SCALING_CHANNEL      | reverb                | "reverb"                                                                    |





## SSL Certificate Configuration

These environment variables are used to specify the SSL certificate and the corresponding private key that are essential for establishing secure TLS/SSL connections in certain  broadcasting setups. This is particularly crucial when using Reverb or similar services  with encrypted connections, ensuring data is securely transmitted over HTTPS.

| Variable            | Description                                                                                                                                                                         |
|---------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| SSL_CERTIFICATE     | Specifies the path to your SSL certificate file. This certificate is used to authenticate and establish a secure connection between the server and the client.                      |
| SSL_CERTIFICATE_KEY | Specifies the path to the private key file corresponding to your SSL certificate. The key is required to confirm the identity of the server and encrypt the data being transmitted. |

In the broadcasting configuration, these variables are used to configure the Guzzle HTTP client with appropriate SSL settings. By providing these files, you enable SSL/TLS encryption for broadcast services, enhancing the security of data in transit.






## Vite Environment Configuration


These Vite environment variables are used to configure the front-end build system and its integration with services such as Reverb for real-time functionality within the application.

| Variable                | Description                                                                                                                                                     |
|-------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| VITE_APP_NAME           | Represents the application's name used within the Vite build process. It is typically a direct reflection of the application's name set in the Laravel backend. |
| VITE_REVERB_APP_KEY     | The Reverb application's key used for authentication. It should mirror the REVERB_APP_KEY variable set in the backend environment configuration.                |
| VITE_REVERB_APP_CLUSTER | The cluster designation for the Reverb setup, which specifies which region or data center the real-time data will be managed through.                           |
| VITE_REVERB_HOST        | Designates the host for the Reverb service. This is typically set from the corresponding backend environment variable REVERB_HOST.                              |
| VITE_REVERB_PORT        | Specifies the port that the Reverb service will run on, consistent with the setup from the backend environment variable REVERB_PORT.                            |
| VITE_REVERB_SCHEME      | Defines the protocol scheme used by the Reverb service, such as "http" or "https", typically mirroring the REVERB_SCHEME variable from the backend.             |




## Queue Worker Configuration

This configuration setting is used to specify the queue connection that should be used by the Laravel application. This is essential for managing asynchronous tasks such as sending emails, processing uploads, or any other task that can be handled in the background.
| Variable| Description|
|---|---|
| QUEUE_CONNECTION| Defines the queue connection that the Laravel application will use. Options include "sync", "database", "redis", etc. The "database" connection is typically used to store jobs in a database table, which is useful for tracking, retrying, or monitoring queued jobs effectively.|



## Authentication and Authorization


Access to HAWKI is restricted for registered users. In a production environment, you usually want to connect to your LDAP directory, OpenID provider, or Shibboleth service to make HAWKI available to your staff and/or students. For simpler setups (e.g., a small project setup for a single course), you can use the built-in Test User Authentication mechanism. This allows defining a small set of pre-allocated users in the local database (set up in advance by you).

>***NOTE:*** If you want a small setup with fixed users but want to allow the users to change their passwords, you can install LDAP (https://github.com/lldap/lldap) on the same machine as HAWKI.

Supported authentication methods:
  - LDAP
  - OIDC (OpenID Connect)
  - Shibboleth

Set the AUTHENTICATION_METHOD variable to one of the following:

```
    AUTHENTICATION_METHOD="LDAP"
    AUTHENTICATION_METHOD="OIDC"
    AUTHENTICATION_METHOD="Shibboleth"
```

According to your authentication method, set the necessary variables as follows:



## LDAP Configuration

| Variable        | Default Value                                  | Description                                                                                                                            |
|-----------------|------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| LDAP_CONNECTION |                                                | Configure the LDAP connection. Currently only "default" is supported (see config/ldap.php)                                             |
| LDAP_HOST       | "ldaps://...de"                                | Hostname of the LDAP server                                                                                                            |
| LDAP_PORT       | "636"                                          | Port number of the LDAP server                                                                                                         |
| LDAP_USERNAME   |                                                | Distinguished Name (DN) used for bind operation                                                                                        |
| LDAP_BIND_PW    | "xxx"                                          | Password to access the LDAP server                                                                                                     |
| LDAP_BASE_DN    | "xxx"                                          | Base DN for the LDAP search                                                                                                            |
| LDAP_TIMEOUT    |                                                | Timeout for LDAP queries in seconds                                                                                                    |
| LDAP_SSL        |                                                | Use SSL to connect to the LDAP server. Not recommended: "true" or "false"                                                              |
| LDAP_TLS        |                                                | Use TLS to connect to the LDAP server. Recommended: "true" or "false"                                                                  |
| LDAP_SASL       |                                                | Use SASL to connect to the LDAP server: "true" or "false"                                                                              |
| LDAP_LOGGING    |                                                | Enable logging of LDAP queries: "true" or "false"                                                                                      |
| LDAP_CACHE      |                                                | Enable caching of LDAP queries: "true" or "false"                                                                                      |
| LDAP_ATTRIBUTES | cn,mail,employeetype,displayname               | Attributes required for data extraction (Username, Email Address, Employee Type, Name)                                                 |
| LDAP_FILTER     | "(\|(sAMAccountName=username)(mail=username))" | Filter required for authentication based on Username                                                                                   |
| CACHE_DRIVER    |                                                | Cache driver for caching LDAP queries: "file", ...                                                                                     |
| TEST_USER_LOGIN | true / false                                   | Reads the test users list in storage folder before LDAP. Set to true for allowing test access and add tester profile to the json file. |




## Shibboleth Configuration

| Variable                    | Default Value                           | Description                                                                                           |
|-----------------------------|-----------------------------------------|-------------------------------------------------------------------------------------------------------|
| SHIBBOLETH_LOGIN_URL        | "Shibboleth.sso/Login?target=login.php" | Path to the Shibboleth login handler `"{$scheme}://{$_SERVER['HTTP_HOST']}/{$loginPath}{$loginPage}"` |
| SHIBBOLETH_LOGOUT_URL       |                                         | URL for Shibboleth logout                                                                             |
| SHIBBOLETH_NAME_VAR         | "displayname"                           | Defined attribute on shibboleth server for name                                                       |
| SHIBBOLETH_EMPLOYEETYPE_VAR | "email"                                 | Defined attribute on shibboleth server for employee type                                              |
| SHIBBOLETH_EMAIL_VAR        | "employeetype"                          | Defined attribute on shibboleth server for email address                                              |






## OpenID Connect (OIDC) Configuration

| Variable              | Default Value  | Description                                                                                    |
|-----------------------|----------------|------------------------------------------------------------------------------------------------|
| OIDC_IDP              | "https://xxx"  | URL of the OpenID Connect Identity Provider                                                    |
| OIDC_CLIENT_ID        | "xxx"          | Client ID for the OIDC application                                                             |
| OIDC_CLIENT_SECRET    | "xxx"          | Client secret for the OIDC application                                                         |
| OIDC_LOGOUT_URI       | "xxx"          | URI for OIDC logout                                                                            |
| OIDC_SCOPES           | profile,email  | Scopes define the level of access that the client is requesting from the authorization server. |
| OIDC_FIRSTNAME_VAR    | "firstname"    | "firstname"                                                                                    |
| OIDC_LASTNAME_VAR     | "lastname"     | "lastname"                                                                                     |
| OIDC_EMAIL_VAR        | "email"        | "email"                                                                                        |
| OIDC_EMPLOYEETYPE_VAR | "employeetype" | "employeetype"                                                                                 |




## External Communication Configuration

These settings control API access to HAWKI models and user token creation capabilities.

| Variable                     | Default Value | Description                                                                                                                                                                                                                                |
|------------------------------|---------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| ALLOW_EXTERNAL_COMMUNICATION | false         | Master switch for external API access. When "true", API requests through external endpoints are permitted. When "false", all external API requests are blocked.                                                                            |
| ALLOW_USER_TOKEN_CREATION    | false         | Controls whether users can create their own API tokens via the web interface. When "true", users can create, view, and revoke tokens through the profile page. When "false", only administrators can create tokens via command line tools. |

>**Security Note:** Both settings must be considered for managing API access:
>- `ALLOW_EXTERNAL_COMMUNICATION=true` and `ALLOW_USER_TOKEN_CREATION=true`: Users can create tokens and use the API
>- `ALLOW_EXTERNAL_COMMUNICATION=true` and `ALLOW_USER_TOKEN_CREATION=false`: Only admin-created tokens can use the API  
>- `ALLOW_EXTERNAL_COMMUNICATION=false`: No API access regardless of token creation setting






## Caching

The following settings allow use a dedicated cache server to speed up some often executed code paths like database queries and so on. Usually a very fast storage like Memcached or Redis is used for this. By default, the local database will be used, which should be fine for most installations. So only change these values if you really need to.

| Variable     | Default Value | Description                                                                                                        |
|--------------|---------------|--------------------------------------------------------------------------------------------------------------------|
| CACHE_STORE  | database      | Cache storage type: "array", "database", "file", "memcached", "redis", "dynamodb", "octane" (see config/cache.php) |
| CACHE_PREFIX |               | Prefix for cache keys (by default calculated from the app name)                                                    |

### Database Cache Configuration
| Variable                 | Default Value | Description                           |
|--------------------------|---------------|---------------------------------------|
| DB_CACHE_TABLE           | cache         | Database table name for cache storage |
| DB_CACHE_CONNECTION      |               | Database connection for cache         |
| DB_CACHE_LOCK_CONNECTION |               | Database connection for cache locks   |

### Memcached Configuration  
| Variable                | Default Value | Description                            |
|-------------------------|---------------|----------------------------------------|
| MEMCACHED_HOST          | 127.0.0.1     | Memcached server hostname              |
| MEMCACHED_PORT          | 11211         | Memcached server port                  |
| MEMCACHED_USERNAME      |               | Memcached username (if required)       |
| MEMCACHED_PASSWORD      |               | Memcached password (if required)       |
| MEMCACHED_PERSISTENT_ID |               | Persistent connection ID for Memcached |

### Redis Cache Configuration
| Variable                    | Default Value | Description                      |
|-----------------------------|---------------|----------------------------------|
| REDIS_CACHE_CONNECTION      | cache         | Redis connection name for cache  |
| REDIS_CACHE_LOCK_CONNECTION | default       | Redis connection for cache locks |

### DynamoDB Configuration
| Variable             | Default Value | Description                   |
|----------------------|---------------|-------------------------------|
| DYNAMODB_CACHE_TABLE | cache         | DynamoDB table name for cache |
| DYNAMODB_ENDPOINT    |               | DynamoDB endpoint URL         |

## Redis Server

Redis configuration is used for Reverb scaling (when `REVERB_SCALING_ENABLED=true`), caching, and optionally as a session store. Redis provides high-performance data storage for real-time features.

| Variable       | Default Value | Description                                                 |
|----------------|---------------|-------------------------------------------------------------|
| REDIS_CLIENT   | phpredis      | PHP library used to access Redis: "phpredis" or "predis"    |
| REDIS_HOST     | localhost     | Redis server hostname                                       |
| REDIS_PORT     | 6379          | Redis server port number                                    |
| REDIS_USERNAME |               | Redis username (Redis 6.0+)                                 |
| REDIS_PASSWORD |               | Redis server password                                       |
| REDIS_DB       | 0             | Redis database number for normal data                       |
| REDIS_CACHE_DB | 1             | Redis database number for cache data                        |
| REDIS_CLUSTER  | redis         | Redis cluster configuration                                 |
| REDIS_PREFIX   |               | Prefix for Redis keys (by default calculated from app name) |






## Email Configuration

The email configuration settings allow the application to send invitation emails to users, enabling them to invite other users to group chats. This feature is currently in beta testing. Ensure that the mail server settings are correctly configured to enable email functionality.

>**Note:** For development and testing, you can use `MAIL_MAILER=log` to log emails instead of sending them.

| Variable          | Default Value | Description                                                                                            |
|-------------------|---------------|--------------------------------------------------------------------------------------------------------|
| MAIL_MAILER       | smtp          | The mailer method to use for sending emails. Options: "smtp", "log", "array", "sendmail"               |
| MAIL_HOST         |               | The hostname of the SMTP server used to send emails                                                    |
| MAIL_PORT         | 587           | The port number used by the SMTP server. Use 465 for SSL or 587 for TLS                                |
| MAIL_USERNAME     |               | The username for authenticating with the SMTP server                                                   |
| MAIL_PASSWORD     |               | The password for authenticating with the SMTP server                                                   |
| MAIL_ENCRYPTION   | tls           | The encryption method used to secure email transmissions. Use 'ssl' for port 465 or 'tls' for port 587 |
| MAIL_FROM_ADDRESS |               | The email address that will appear as the sender of the invitation emails                              |
| MAIL_FROM_NAME    | HAWKI         | The display name that will appear as the sender of the invitation emails                               |




## Encryption Configuration

For enhanced security, HAWKI utilizes individual salts for each component to ensure that data is encrypted uniquely. While not mandatory, using unique hash keys for each component is recommended to maximize the security of user data, invitations, AI components, passkeys, and backups.

>**Security Note:** All salt values should be base64-encoded random strings. Generate new salts for each installation to ensure maximum security.

| Variable                 | Default Format                 | Description                                                                                        |
|--------------------------|--------------------------------|----------------------------------------------------------------------------------------------------|
| USERDATA_ENCRYPTION_SALT | base64:someRandomSalt==        | The salt used specifically for encrypting user data                                                |
| INVITATION_SALT          | base64:someOtherRandomSalt==   | The salt used for encrypting invitations data                                                      |
| AI_CRYPTO_SALT           | base64:someVeryCoolSalt==      | Used to generate a derived key for the AI messages in the group chat                               |
| PASSKEY_SALT             | base64:somePrettyAwesomeSalt== | The salt used for encrypting passkey data, contributing to robust password and credential security |
| BACKUP_SALT              | base64:someLegendarySalt==     | The salt used to encrypt backup data, ensuring their security during storage and transfer          |






## Links

| Variable         | Description                                |
|------------------|--------------------------------------------|
| IMPRINT_LOCATION | The URL to your organization imprint page. |


