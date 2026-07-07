# Upgrading to version %%VERSION%%

## Overview

[//]: # (Briefly describe what makes this upgrade different from a routine update)

[//]: # (and why manual intervention is required.)

## Steps

### [OPTIONAL] 1. Add additional file pre-processing dependencies

> **Note:** If you're running HAWKI in Docker, all of these dependencies are already included in the image and pre-configured. You only need to install them manually if you're running HAWKI on your system directly.

HAWKI can now pre-process a wider range of image and document formats before sending them to the configured file converter. These tools are **optional** — HAWKI detects them automatically from `$PATH` if installed, and skips pre-processing gracefully if they are not present.

#### `rsvg-convert` (for SVG support)

Enables conversion of SVG files to PNG before processing.

```bash
# Debian / Ubuntu
sudo apt install librsvg2-bin

# Red Hat / CentOS
sudo yum install librsvg2-tools

# Fedora
sudo dnf install librsvg

# Alpine Linux
sudo apk add librsvg

# Arch Linux
sudo pacman -S librsvg
```

#### ImageMagick (for exotic raster formats)

Enables conversion of `.ai`, `.eps`, `.ps`, `.psd`, `.tiff`, `.tif`, `.bmp`, and `.ico` files to JPEG before processing.

```bash
# Debian / Ubuntu
sudo apt install imagemagick ghostscript

# Red Hat / CentOS
sudo yum install imagemagick ghostscript

# Fedora
sudo dnf install imagemagick ghostscript

# Alpine Linux
sudo apk add imagemagick ghostscript

# Arch Linux
sudo pacman -S imagemagick ghostscript
```

#### Ghostscript (required for PostScript formats)

If you install ImageMagick for exotic format support, you must also install `ghostscript`. ImageMagick internally delegates PostScript-based formats (`.ai`, `.eps`, `.ps`) to Ghostscript — without it, those specific formats will be skipped gracefully but won't be converted.

The commands above already include `ghostscript` alongside `imagemagick` for all distributions.

If you need to install the binaries to a non-standard location, you can override the detected paths via environment variables:

```
FILE_CONVERTER_BINARY_RSVG_CONVERT=/path/to/rsvg-convert
FILE_CONVERTER_BINARY_IMAGE_MAGICK=/path/to/convert
FILE_CONVERTER_BINARY_GHOSTSCRIPT=/path/to/gs
```

## 2. Remove no longer required env variables:

In this version we introduced a better solution to propagate the "reverb" (websocket) configuration to the frontend. Therefore the values must no longer be available at build time. For you, there is no need to intervene, but you probably like your `.env` file to be clean and tidy, so you can remove the `VITE_REVERB_*` variables from there.

## 3. Upgrade PHP to 8.3

The minimum PHP version is now **8.3** (raised from 8.2). Before deploying, ensure your host or Docker base image provides it.

> **Note:** If you are running HAWKI in Docker, the official image (`neunerlei/php-nginx:8.3`) is already updated and no further action is needed.

The following PHP extensions are now explicitly required — enable them if they are not already active on your server:

```
curl, dom, fileinfo, libxml, openssl, zip, gd
```

## 4. Configure encryption salts before migrating

This version introduces five application-level encryption salts. These **must be set in your `.env` before running `php artisan migrate`** — if they are missing, `SaltProvider` will auto-generate them at runtime, but the values will differ on every boot, permanently breaking any data encrypted with the previous values.

Generate each salt with `openssl rand -base64 32` and add them to your `.env`:

```
APP_ENCRYPTION_SALT_USERDATA=
APP_ENCRYPTION_SALT_INVITATION=
APP_ENCRYPTION_SALT_AI_CRYPTO=
APP_ENCRYPTION_SALT_PASSKEY=
APP_ENCRYPTION_SALT_BACKUP=
```

> **Note:** If you are running HAWKI in Docker, these salts must also be present in `_docker_production/.env` before the first container start after upgrading.

## 5. Run all database migrations

This version introduces many new tables. After setting the salts above, run:

```bash
php artisan migrate
```

## 6. Sync AI tools, MCP servers, and model config into the database

AI tools and MCP servers have been migrated from static configuration files to database-backed models. After migrating, populate them from your existing configuration:

```bash
php artisan ai:tools:sync
php artisan ai:config:sync
```

## 7. Update custom code referencing removed classes

The following classes have been removed. Update any custom code that references them:

| Removed | Replacement |
|---|---|
| `FileConverterFactory` | Inject `FileConverterInterface` directly; call `isAvailable()` to check if a converter is configured |
| `AttachmentService` / `AttachmentFactory` | `AttachmentRepository` |
| `MessageHandlerFactory` | Resolve `PrivateMessageHandler` / `GroupMessageHandler` from the Laravel service container |
| `ExternalCommunicationCheck` middleware | `ExternalAccessMiddleware` or `AppAccessMiddleware` — configure feature toggles in `config/external_access.php` |

## 8. Update custom code reading `AiModel` attributes

The following `AiModel` attributes now return **typed value objects** instead of raw arrays or strings. Any code reading them with direct array access must be updated to use the value object API:

`input`, `output`, `parameters`, `status`, `demand`, `capabilities`, `settings`

## 9. Update custom storage service calls

All method signatures on `FileStorageService` and `AvatarStorageService` have changed as part of the storage layer overhaul. Any custom code calling these services directly must be updated to use the new API.

## 10. Update custom AI provider implementations

If you have custom AI provider implementations, they must now implement `ProviderAdapterInterface` and be registered with `ProviderAdapterRegistry`. The previous inheritance-based approach is no longer supported.
