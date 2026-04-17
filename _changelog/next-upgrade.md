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
