# Upgrading to version %%VERSION%%

## Overview

> **BEWARE**
>
> This upgrade introduces **LOTS of breaking changes**! If you have customized HAWKI to match your needs, we strongly recommend to **NOT UPGRADE** to this version. Version %%VERSION%% lays the foundation for a new major version; v3.0, it cleans up many legacy APIs and code paths. If you have custom code that depended on those, it will break after upgrading. However, we still have a lot to refactor,
> so we can not guarantee that the versions until v3.0 will not break your custom code again. Therefore, if you have custom code, we recommend to **stay on your current version** until v3.0 is released and then upgrade directly to v3.0.
>
> **v3.0 is expected to be released in Q4 of 2026.**
>
> If you have not customized HAWKI, you can safely upgrade to this version. Follow the steps below to ensure a smooth upgrade.

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

> **Note:** If you're running HAWKI in Docker, the official image (`neunerlei/php-nginx:8.3`) is already updated and no further action is needed.

The minimum PHP version is now **8.3** (raised from 8.2). Before deploying, ensure your host or Docker base image provides it.

> **Note:** If you are running HAWKI in Docker, the official image (`neunerlei/php-nginx:8.3`) is already updated and no further action is needed.

The following PHP extensions are now explicitly required — enable them if they are not already active on your server:

```
curl, dom, fileinfo, libxml, openssl, zip, gd
```

## 4. Run all database migrations

This version introduces many new tables. After setting the salts above, run:

```bash
php artisan migrate
```

## 5. Sync AI tools, MCP servers, and model config into the database

AI tools and MCP servers have been migrated from static configuration files to database-backed models. After migrating, populate them from your existing configuration:

```bash
php artisan ai:config:sync
php artisan ai:tools:sync
php artisan ai:models:check-status
php artisan ai:tools:check-status
```
