# File Converter

HAWKI can extract text from uploaded documents (PDFs, Word files, spreadsheets, presentations) so AI models can read the content without the user copy-pasting it. Extraction is handled by a pluggable converter pipeline configured via `config/file_converter.php`.

## `FileConverterInterface`

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

`isValidConfig` is static — returns true when the config array has all required keys (used by the service provider before instantiation). `isAvailable()` returns true when the converter's backing service or binary is reachable. `canConvertMimetype` checks against `getAllowedMimeTypes()` by default. `wouldLikeSomeoneElseToConvertMimetype` signals that another converter is preferred for this type even though this one could technically handle it (used by `KreuzbergConverter` to defer SVG to local pre-processing). `convert` returns a collection of extracted file references (typically a Markdown text file, possibly images).

To use a converter, resolve `FileConverterInterface` from the container and always check `isAvailable()` first:

```php
public function __construct(private readonly FileConverterInterface $converter) {}

public function processDocument(FileReference $file): FileCollection
{
    if ($this->converter->isAvailable() && $this->converter->canConvertMimetype($file->getMimeType())) {
        return $this->converter->convert($file);
    }
    return new FileCollection();
}
```

:::caution
`FileConverterFactory` has been removed. Always request `FileConverterInterface` from the container — the service provider assembles and binds the correct pipeline for you.
:::

## Built-in converters

- **`HawkiDocConverter`** — default internal converter. Calls HAWKI's own document conversion microservice. Config keys: `HAWKI_FILE_CONVERTER_API_URL`, `HAWKI_FILE_CONVERTER_API_KEY`. Selected by `FILE_CONVERTER=hawki_converter`.
- **`GwdgDoclingConverter`** — connects to the GWDG Academic Cloud's Docling conversion API. Config keys: `GWDG_FILE_CONVERTER_API_URL` (override), `GWDG_API_KEY`.
- **`KreuzbergConverter`** — integrates with a self-hosted [Kreuzberg](https://github.com/Goldziher/kreuzberg) service. Config key: `KREUZBERG_FILE_CONVERTER_API_URL`.
- **`NullFileConverter`** — the fallback when no configured converter passes `isValidConfig()` or `isAvailable()`. Returns an empty `FileCollection` and `false` from `isAvailable()`. The system remains functional — attachments are stored and downloadable, but their text content is not extracted for AI context.

`AbstractFileConverter` is the base class for the three API-backed converters: provides `setConfig`, default `canConvertMimetype`, and `wouldLikeSomeoneElseToConvertMimetype` returning `false`. Concrete implementations provide `isValidConfig`, `isAvailable`, `convert`, `getAllowedMimeTypes`.

## `ImagePreProcessingConverter` — the wrapping layer

`App\Services\FileConverter\Utils\ImagePreProcessingConverter` wraps every converter. The service provider always assembles the pipeline as `ImagePreProcessingConverter(concreteConverter)`, so `FileConverterInterface` in the container always resolves to this wrapper.

The wrapper intercepts image formats that external conversion APIs typically cannot handle natively (SVG, TIFF, PSD, EPS, AI, PS, BMP, ICO) and converts them locally using CLI tools before passing the result to the inner converter. Local tools: `rsvg-convert` (librsvg2-bin) for SVG; ImageMagick `convert` for TIFF/PSD/BMP/ICO; ImageMagick + Ghostscript for EPS/AI/PS. Binary paths are configurable via `config/file_converter.php` (`file_converter.binaries.*`).

:::warning Ghostscript required for PostScript formats
EPS, AI, and PS files require both ImageMagick and Ghostscript. ImageMagick delegates PostScript rendering to Ghostscript. Install with `apt-get install imagemagick ghostscript`.
:::

Binary availability is cached for 24 hours to avoid repeated subprocess calls. Clear the application cache after installing or removing binaries to force re-detection.

The decision logic: if the inner converter is available and can handle the MIME and does not want someone else to handle it, pass directly. If `rsvg-convert` is available and the file is SVG, convert locally to PNG then optionally forward. If ImageMagick is available and the file is TIFF/PSD/EPS/AI/PS/BMP/ICO (with Ghostscript for the PostScript ones), convert to JPEG locally (one file per page for multi-page formats), then optionally forward. Otherwise return an empty `FileCollection`.

## Selecting a converter

The active converter is selected by the `FILE_CONVERTER` environment variable (default `hawki_converter`):

```bash
FILE_CONVERTER=gwdg_docling
GWDG_API_KEY=your_key_here
```

`FileConverterServiceProvider` reads `config/file_converter.php`, finds the entry matching `FILE_CONVERTER`, validates the config with `isValidConfig()`, checks `isAvailable()`, wraps the result with `ImagePreProcessingConverter`, and binds it as `FileConverterInterface`.

## Adding a custom converter

1. Implement `FileConverterInterface` (or extend `AbstractFileConverter`).
2. Register it in `config/file_converter.php`:

```php
'converters' => [
    'my_converter' => [
        'api_url' => env('MY_CONVERTER_URL'),
        'api_key' => env('MY_CONVERTER_KEY'),
        'class'   => \App\Services\FileConverter\Handlers\MyConverter::class,
    ],
],
```

3. Set `FILE_CONVERTER=my_converter` in your environment. Autoloading is handled by Composer PSR-4 — no registration step beyond the config entry.

See [Extending HAWKI](../../200-Concepts/220-Extending-HAWKI.md).

## Diagnosing available types

The `filestorage:converter:types:list` artisan command prints the MIME types and extensions the currently active converter (including the `ImagePreProcessingConverter` wrapper) will accept. Run after changing the active converter or installing new system binaries to verify the expected types are available.
