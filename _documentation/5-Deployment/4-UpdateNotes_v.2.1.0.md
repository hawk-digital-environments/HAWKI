
## Updating to v2.1.0

If you have already implemented HAWKI 2.0.x on your machine, there are a few steps needed to update to version 2.1.0

0- **Create a backup**

**Important:** Before you begin, please ensure that you create a backup of your project’s contents.

- The new version introduces breaking changes that may overwrite or replace existing data.
- The `model_providers.php` file is now **no longer ignored by Git**, and related variables are now stored in the `.env` file.
- Make sure to **securely save your API keys** before proceeding with the update.

1- **As usual, update the project content.**

2- **Set the updated .env variables:**

There are some new .env variables that need to be added. Below you'll find the list of new variables. for more information you can refer to the updated `.env.example` file

**HAWKI Profile Variables:**

Note: The variables are valid only before migration.
To use a custom avatar for HAWKI, add it in the `public/img/` folder before database migration.

```
# -------------------------
# MIGRATION ATTRIBUTES
# -------------------------
# Remember after migrating the database you can't change the migration attributes anymore.
# If you want to change the HAWKI_AVATAR first you need to place the file in /public/img
# folder and update the name here.

HAWKI_NAME='HAWKI'
HAWKI_USERNAME='HAWKI'
HAWKI_AVATAR='hawkiAvatar.jpg'
```

**AI Connection**

As of this version you can secure your API keys in the .env file.

```
# ==========================
# AI CONNECTION
# ==========================
#
# Add your AI Provider API Keys here.
# This section includes a list of hidden attributes for costumization of each model and provider.
# For the complete list of attributes refer to HAWKI Documentation.

OPENAI_API_KEY=
GWDG_API_KEY=
GOOGLE_API_KEY=
OPEN_WEB_UI_API_KEY=

```

**File Converter**

This version utilizes a file converter to prepare document files as context for the AI model.
You can either use HAWKI File Converter or the Docling endpoint provided by GWDG.
To host the HAWKI File Converter Section.

```
# ==========================
# FILE CONVERTER
# ==========================
#
# Define your file converter here.
# You can choose betwee HAWKI Toolkit File Converter, GWDG SAIA Docling.
# To use Gwdg Docling you'll need to have the GWDG_API_KEY.
#
# FILE_CONVERTER:                   The converter you prefer to use "hawki_converter", "gwdg_docling"
# HAWKI_FILE_CONVERTERS_API_URL     URL to the Hosted HAWKI converter.
# GWDG_FILE_CONVERTER_API_URL       URL to the GWDG converter. 'https://chat-ai.academiccloud.de/v1/documents/convert'

FILE_CONVERTER=hawki_converter
HAWKI_FILE_CONVERTER_API_URL=
HAWKI_FILE_CONVERTER_API_KEY=
GWDG_FILE_CONVERTER_API_URL='https://chat-ai.academiccloud.de/v1/documents/convert' # DEFAULT VALUE

```

**DATABASE**

```
# ===============
# Database server
# ===============

DB_BACKUP_INTERVAL="daily"

```

**File System**

Different file storage disk can be selected for Profile and Room Avatars and File Storage (Attachments).

```

# ==================
# Filesystem Storage
# ==================
#
# Uploaded media files are typically stored on disk and served from an asset web server.
# However, to simplify the setup the uploaded files will be served by PHP by default,
# though thos is not so good for performance. Alternateively Amazon S3 could be used.
#
# NOTE: If you want to serve media files with your web server, choose "public" for
# FILESYSTEM_DISK. In this case make the server serve the files from the "app/public"
# directory. The HTTP address must be your APP_URL followed by "/storage".
#
#  - FILESYSTEM_DISK:               Default Storage type: "local", "public", "s3", "sftp", "nextcloud"
#  - STORAGE_DISK:                  File Storage type: "local", "public", "s3", "sftp", "nextcloud"
#  - AVATAR_STORAGE:                Avatar Storage type: "local", "public", "s3", "sftp", "nextcloud"
#  - REMOVE_FILES_AFTER_MONTHS:     Remove the storage files after an specific expiry date.
# 
#  - S3_ACCESS_KEY:                 Access Key to S3 Server 
#  - S3_SECRET_KEY:                 Secret Key to S3 Server
#  - S3_REGION:                     Region of S3 Server
#  - S3_ENDPOINT:                   URL to S3 Server
#  - S3_DEFAULT_BUCKET:             Bucket name
#  - 
#  - NEXTCLOUD_BASE_URL:            URL to Nextcloud server
#  - NEXTCLOUD_BASE_PATH:           Base Path to Nextcloud storage folder
#  - NEXTCLOUD_USERNAME:            Nextcloud Username
#  - NEXTCLOUD_PASSWORD:            Nextcloud Password
# 
#  - SFTP_HOST:                     SFTP Server Host
#  - SFTP_PORT:                     SFTP Server Port
#  - SFTP_USERNAME:                 SFTP Server Username
#  - SFTP_PASSWORD:                 SFTP Server Password
#  - SFTP_BASE_PATH:                Base Path to SFTP Server storage folder
```


3- **File Converter**

The new Attachments feature in HAWKI allows user to upload files in the chat. But since the models mostly do not accept document files as input, we need to first convert these to text.
This task can be done either by the new converter module in HAWKI Toolkit or be the provided document endpoint from GWDG.
If you do not uncomment the FILE_CONVERTER variables in .env file, HAWKI turns off the document support for attachments automatically.

If you prefer to use HAWKI FIle Converter, and you are not using the docker deployment you can host the converter separately from the project.
For more information refer to the [File Converter Repo](https://github.com/hawk-digital-environments/hawki-toolkit-file-converter) of this documentation.



4- **Reinstall Composer and Node packages.**

The new packages include:
Composer: 
```
league/flysystem-aws-s3-v3": "^3.29     Handling S3 Storage
league/flysystem-sftp-v3": "^3.30       Handling SFTP Storage
league/flysystem-webdav": "^3.0         Handling Nextcloud Storage
spatie/laravel-backup": "^9.            Handling database and server backups
```

Node:
```
pdfjs-dist                              Rendering PDF Files
docx-preview                            Rendering Docx Files
```

To install the packages, run:

```
composer update
composer install
npm install
npm run build
```

5- **Migrate the database tables.**

To add new data models to your database run:
```
php hawki migrate
```

6- **Migrate Avatars**

To migrate old file structure for profile and room avatars into new system, run:

```
php artisan migrate:avatars
```


6- **Create Schedule Service**

Whether you are using Apache or Nginx, create a new file in `/etc/systemd/system` for schedule worker and call it "schedule-worker.service". Insert this content:

>**Don't forget to update paths: `/var/www/html/hawki-project`**

```
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/hawki-project
ExecStart=/usr/bin/php /var/www/html/hawki-project/artisan schedule:work
Restart=always
TimeoutSec=300
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
```


Reload Systemd Manager Configuration

```
sudo systemctl daemon-reload
```

Enable services:

```
sudo systemctl enable schedule-worker.service
```

Start the Services:

```
sudo systemctl start schedule-worker.service
```

Check Status (to ensure it has started correctly):

```
sudo systemctl status schedule-worker.service
```

By default, model status update runs every 15 minutes.
In order to check the models statuses run:
```
php artisan check:model-status
```

7- Finally clear the cache by running:

```
php hawki clear-cache
```

At this point, the new version should be ready and running on your machine.


---
