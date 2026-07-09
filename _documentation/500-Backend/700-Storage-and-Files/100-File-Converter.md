---
sidebar_position: 2
---

# File Converter

HAWKI can extract text from uploaded documents (PDFs, Word files, spreadsheets, presentations) so that AI models can read the content without the user having to copy-paste it. This extraction is handled by a pluggable converter pipeline configured via `config/file_converter.php`.

## The `FileConverterInterface` Contract

`App\Services\FileConverter\Interfaces\FileConverterInterface` is the single contract all converters implement:

```php
interface FileConverterInterface
{
    public static function isValidConfig(array $config): bool;
    public function setConfig(array $config): void;
    public function convert(FileReference $file): FileCollection;
    public function isAvailable(): bool;
    public function getAllowedMimeTypes(): array;
    public function canConvertMimetype(string $mimetype): bool;
    public function wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool;
}
```

| Method | Description |
|---|---|
| `isValidConfig(array $config): bool` | Static: returns true when the config array has all required keys (used by the service provider before instantiation) |
| `setConfig(array $config): void` | Called by the service provider immediately after construction to inject the resolved config |
| `isAvailable(): bool` | Returns true when the converter's backing service or binary is reachable and ready |
| `canConvertMimetype(string $mimetype): bool` | Returns true when the converter supports the given MIME type |
| `wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool` | Signals that another converter is preferred for this type, even though this one could technically handle it |
| `convert(FileReference $file): FileCollection` | Performs the conversion; returns a collection of extracted file references (typically a Markdown text file, possibly images) |

:::tip
To use the converter, resolve `FileConverterInterface` from the container and always check `isAvailable()` first:

```php
public function __construct(private readonly FileConverterInterface $converter) {}

public function processDocument(FileReference $file): FileCollection
{
    if ($this->converter->isAvailable() && $this->converter->canConvertMimetype($file->getMimeType())) {
        return $this->converter->convert($file);
    }
    return new FileCollection(); // empty — no conversion
}
```
:::

:::caution
`FileConverterFactory` has been removed. Do not try to instantiate converters manually. Always request `FileConverterInterface` from the container — the service provider assembles and binds the correct pipeline for you.
:::

## Built-in Converters

### `HawkiDocConverter`

`App\Services\FileConverter\Handlers\HawkiDocConverter` — the default internal converter. Calls HAWKI's own document conversion microservice.

Config keys:
- `HAWKI_FILE_CONVERTER_API_URL` — base URL of the conversion service
- `HAWKI_FILE_CONVERTER_API_KEY` — authentication key

This is the `FILE_CONVERTER=hawki_converter` option in `config/file_converter.php`.

### `GwdgDoclingConverter`

`App\Services\FileConverter\Handlers\GwdgDoclingConverter` — connects to the GWDG Academic Cloud's Docling conversion API at `https://chat-ai.academiccloud.de/v1/documents/convert`.

Config keys:
- `GWDG_FILE_CONVERTER_API_URL` — override the endpoint (default points to GWDG)
- `GWDG_API_KEY` — the GWDG API key

### `KreuzbergConverter`

`App\Services\FileConverter\Handlers\KreuzbergConverter` — integrates with a self-hosted [Kreuzberg](https://github.com/Goldziher/kreuzberg) conversion service.

Config key: `KREUZBERG_FILE_CONVERTER_API_URL`

### `NullFileConverter`

The fallback when no configured converter passes `isValidConfig()` or `isAvailable()`. Returns an empty `FileCollection` from `convert()` and `false` from `isAvailable()`. The system remains functional — attachments are stored and can be downloaded, but their text content will not be extracted for AI context.

## `AbstractFileConverter`

`App\Services\FileConverter\Handlers\AbstractFileConverter` is the base class for the three API-backed converters. It provides:

- `setConfig(array $config): void` — stores the config array in `$this->config`
- `canConvertMimetype(string $mimetype): bool` — default implementation that checks against `getAllowedMimeTypes()`
- `wouldLikeSomeoneElseToConvertMimetype(string $mimetype): bool` — returns `false` by default

Concrete implementations provide `isValidConfig()`, `isAvailable()`, `convert()`, and `getAllowedMimeTypes()`.

## `ImagePreProcessingConverter` — The Wrapping Layer

`App\Services\FileConverter\Utils\ImagePreProcessingConverter` wraps every converter. The service provider always assembles the pipeline as `ImagePreProcessingConverter(concreteConverter)`, so `FileConverterInterface` in the container always resolves to this wrapper.

The wrapper intercepts image formats that external conversion APIs typically cannot handle natively (SVG, TIFF, PSD, EPS, AI, PS, BMP, ICO) and converts them locally using CLI tools before passing the result to the inner converter.

### Local conversion tools

| Format | Tool | Package | Detection |
|---|---|---|---|
| SVG | `rsvg-convert` | `librsvg2-bin` | `which rsvg-convert` |
| TIFF, PSD, BMP, ICO | ImageMagick `convert` | `imagemagick` | `which convert` |
| EPS, AI, PS | ImageMagick + Ghostscript | `imagemagick`, `ghostscript` | `which gs` |

Binary paths are configurable via `config/file_converter.php` (keys `file_converter.binaries.rsvg_convert` and `file_converter.binaries.image_magick`), controlled by the `FILE_CONVERTER_BINARY_RSVG_CONVERT` and `FILE_CONVERTER_BINARY_IMAGE_MAGICK` environment variables.

:::warning Ghostscript required for PostScript formats
EPS, AI, and PS files require **both** ImageMagick and Ghostscript. ImageMagick delegates PostScript rendering to Ghostscript. If Ghostscript is not installed, these formats will not be accepted for conversion even if ImageMagick is present.

Install with: `apt-get install imagemagick ghostscript`
:::

Binary availability is cached for 24 hours to avoid repeated subprocess calls. Clear the application cache after installing or removing binaries to force re-detection.

### Conversion decision logic

```
Is the inner converter available AND able to handle this MIME type?
  AND does it NOT want someone else to convert it?
    → Pass directly to inner converter

Is rsvg-convert available AND the file is SVG?
    → Convert SVG → PNG locally, then optionally forward PNG to inner converter

Is ImageMagick available AND the file is TIFF/PSD/EPS/AI/PS/BMP/ICO?
  (EPS/AI/PS also require Ghostscript)
    → Convert to JPEG locally (one file per page for multi-page formats)
    → Optionally forward each JPEG to inner converter if it supports image/jpeg

Fallback → return empty FileCollection
```

The `wouldLikeSomeoneElseToConvertMimetype()` hook allows converters like `KreuzbergConverter` to signal that SVG should go through local pre-processing (rsvg-convert → PNG) even though the converter technically accepts SVG, because pre-processing produces a higher-quality result.

## Selecting and Configuring Converters

The active converter is selected by the `FILE_CONVERTER` environment variable (default: `hawki_converter`). The fallback is also `hawki_converter`.

```bash
# .env
FILE_CONVERTER=gwdg_docling
GWDG_API_KEY=your_key_here
```

`FileConverterServiceProvider` reads `config/file_converter.php`, finds the entry matching `FILE_CONVERTER`, validates the config with `isValidConfig()`, checks `isAvailable()`, wraps the result with `ImagePreProcessingConverter`, and binds it as `FileConverterInterface` in the container.

## Adding a Custom Converter

1. Implement `FileConverterInterface` (or extend `AbstractFileConverter`).
2. Register it in `config/file_converter.php` under a new key:

```php
'converters' => [
    // ...existing converters...
    'my_converter' => [
        'api_url' => env('MY_CONVERTER_URL'),
        'api_key' => env('MY_CONVERTER_KEY'),
        'class' => \App\Services\FileConverter\Handlers\MyConverter::class,
    ],
],
```

3. Set `FILE_CONVERTER=my_converter` in your environment.
4. Autoloading is handled by Composer PSR-4 — no registration step beyond the config entry.

## Diagnosing Available Types

The `filestorage:converter:types:list` artisan command prints the MIME types and extensions the currently active converter (including the `ImagePreProcessingConverter` wrapper) will accept:

```bash
bin/env artisan filestorage:converter:types:list
```

Run this after changing the active converter or installing new system binaries to verify the expected types are available.
