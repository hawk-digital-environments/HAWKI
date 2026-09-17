<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Resolves logos from svgl and validates SVGs before they are stored in provider.icon.
 */
class ProviderIconService
{
    public const MAX_BYTES = 262144;

    /**
     * The svgl catalogue and its SVG files change rarely, so both are kept for a day.
     */
    public const CACHE_TTL = 86400;

    public function search(string $search = ''): array
    {
        $aiOnly = '' === trim($search);
        $icons = Cache::remember($aiOnly ? 'admin.svgl.ai.v2' : 'admin.svgl.all.v2', self::CACHE_TTL, function () use ($aiOnly): array {
            try {
                $entries = Http::connectTimeout(3)->timeout(10)->get($aiOnly ? 'https://api.svgl.app/category/AI' : 'https://api.svgl.app')->throw()->json();

                if (!\is_array($entries) || !array_is_list($entries)) {
                    throw new \UnexpectedValueException('Invalid svgl catalogue.');
                }

                $icons = [];

                foreach ($entries as $entry) {
                    if (!\is_array($entry) || ($aiOnly && !\in_array('AI', (array) ($entry['category'] ?? []), true))) {
                        continue;
                    }

                    $route = $entry['route'] ?? null;
                    $light = \is_array($route) ? ($route['light'] ?? null) : $route;
                    $dark = \is_array($route) ? ($route['dark'] ?? null) : null;

                    if (!\is_int($entry['id'] ?? null) || !\is_string($entry['title'] ?? null)
                        || !$this->isSvglUrl($light) || (null !== $dark && !$this->isSvglUrl($dark))) {
                        continue;
                    }

                    $icons[] = ['id' => $entry['id'], 'title' => $entry['title'], 'light' => $light, 'dark' => $dark];
                }

                usort($icons, static fn (array $a, array $b) => strcasecmp($a['title'], $b['title']));

                return $icons;
            } catch (\Throwable $exception) {
                report($exception);
                abort(502, __('admin.icons.unavailable'));
            }
        });

        return array_values(array_filter($icons, static fn (array $icon) => '' === trim($search) || false !== mb_stripos($icon['title'], trim($search))));
    }

    public function uploadRules(): array
    {
        return ['required', 'file', 'max:256', 'extensions:svg'];
    }

    public function upload(UploadedFile $file): array
    {
        return ['source' => 'upload', 'title' => mb_substr($file->getClientOriginalName(), 0, 255),
            'svg' => $this->validateSvg($file->getContent()), 'svg_dark' => null];
    }

    /**
     * An unchanged icon needs no network request. Client-supplied remote URLs are never fetched.
     */
    public function resolve(?array $value, ?array $current): ?array
    {
        if (null === $value || [] === $value) {
            return null;
        }

        $comparison = $value;
        $original = $current ?? [];
        ksort($comparison);
        ksort($original);

        if ($comparison === $original) {
            return $current;
        }

        if ('svgl' === ($value['source'] ?? null)) {
            foreach ($this->search(\is_string($value['title'] ?? null) ? $value['title'] : '') as $icon) {
                if ($icon['id'] === ($value['svgl_id'] ?? null) && $icon['title'] === ($value['title'] ?? null)) {
                    return ['source' => 'svgl', 'svgl_id' => $icon['id'], 'title' => $icon['title'],
                        'svg' => $this->download($icon['light']),
                        'svg_dark' => $icon['dark'] ? $this->download($icon['dark']) : null];
                }
            }

            throw ValidationException::withMessages(['icon' => __('admin.icons.choose_again')]);
        }

        if ('upload' !== ($value['source'] ?? null) || !\is_string($value['svg'] ?? null)
            || (isset($value['title']) && (!\is_string($value['title']) || mb_strlen($value['title']) > 255))) {
            throw ValidationException::withMessages(['icon' => __('admin.icons.invalid')]);
        }

        return ['source' => 'upload', 'title' => $value['title'] ?? null,
            'svg' => $this->validateSvg($value['svg']), 'svg_dark' => null];
    }

    /**
     * Accept static, self-contained SVG drawings only, without active content or external references.
     */
    public function validateSvg(string $svg): string
    {
        $invalid = static fn () => throw ValidationException::withMessages(['icon' => __('admin.icons.invalid')]);

        if ('' === trim($svg) || mb_strlen($svg) > self::MAX_BYTES || preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            $invalid();
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();

            if (!$document->loadXML($svg, \LIBXML_NONET) || $document->doctype
                || 'svg' !== $document->documentElement?->localName
                || 'http://www.w3.org/2000/svg' !== $document->documentElement->namespaceURI) {
                $invalid();
            }

            if (0 < (new \DOMXPath($document))->query('//processing-instruction()')->length) {
                $invalid();
            }

            $tags = explode(' ', 'svg style image g path rect circle ellipse line polyline polygon defs linearGradient radialGradient stop clipPath mask pattern use symbol title desc filter feGaussianBlur feOffset feBlend feColorMatrix feComposite feFlood feMerge feMergeNode');
            $attributes = explode(' ', 'id class style version space enable-background mask-type mix-blend-mode display visibility viewBox width height x y x1 y1 x2 y2 cx cy r rx ry d points fill fill-opacity fill-rule stroke stroke-width stroke-linecap stroke-linejoin stroke-miterlimit stroke-dasharray stroke-dashoffset stroke-opacity opacity transform gradientTransform gradientUnits spreadMethod offset stop-color stop-opacity clip-path clip-rule mask maskUnits maskContentUnits patternUnits patternContentUnits patternTransform preserveAspectRatio href filter filterUnits primitiveUnits in in2 result stdDeviation dx dy mode type values operator k1 k2 k3 k4 flood-color flood-opacity color color-interpolation-filters');

            foreach ($document->getElementsByTagName('*') as $element) {
                if ('http://www.w3.org/2000/svg' !== $element->namespaceURI || !\in_array($element->localName, $tags, true)) {
                    $invalid();
                }

                if ('style' === $element->localName) {
                    // svgl sometimes adds these redundant theme rules. The selected variant already sets the theme.
                    $css = preg_replace('/@media\s*\(prefers-color-scheme:\s*(?:light|dark)\)\s*\{\s*:root\s*\{\s*filter:\s*none;?\s*\}\s*\}/', '', $element->textContent);
                    $remaining = preg_replace_callback('/[.#][\p{L}\w-]+(?:\s*,\s*[.#][\p{L}\w-]+)*\s*\{([^{}]*)\}/u', function ($match) use ($invalid): string {
                        $this->validateStyle($match[1], $invalid);

                        return '';
                    }, $css);

                    if ('' !== trim($remaining)) {
                        $invalid();
                    }

                    $element->textContent = $css;
                }

                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $name = $attribute->localName;
                    $content = trim($attribute->value);

                    if (str_starts_with($attribute->name, 'data-')) {
                        $element->removeAttributeNode($attribute);

                        continue;
                    }

                    if ('style' === $name) {
                        $this->validateStyle($content, $invalid);

                        continue;
                    }

                    if ('image' === $element->localName && 'href' === $name && str_starts_with($content, 'data:image/png;base64,')) {
                        $bytes = base64_decode(mb_substr($content, 22), true);

                        $size = false === $bytes ? false : @getimagesizefromstring($bytes);

                        if (false === $size || 'image/png' !== $size['mime']) {
                            $invalid();
                        }

                        continue;
                    }

                    if (!\in_array($name, $attributes, true)
                        || ($attribute->namespaceURI && !\in_array($attribute->namespaceURI, ['http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace'], true))
                        || ('href' === $name && !preg_match('/^#[\p{L}_][\p{L}\p{N}_:.-]*$/uD', $content))
                        || (preg_match('/url\s*\(/i', $content) && !preg_match('/^url\(#[\p{L}_][\p{L}\p{N}_:.-]*\)$/uD', $content))
                        || preg_match('/[\\\\]|(?:https?|data|javascript):/i', $content)) {
                        $invalid();
                    }
                }
            }

            return $document->saveXML($document->documentElement);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function validateStyle(string $css, \Closure $invalid): void
    {
        $allowed = explode(' ', 'fill fill-rule fill-opacity clip-rule clip-path stroke stroke-width stroke-opacity stroke-linecap stroke-linejoin stroke-miterlimit stroke-dasharray stroke-dashoffset opacity display visibility mask-type mix-blend-mode stop-color stop-opacity filter color flex line-height enable-background');

        foreach (explode(';', $css) as $declaration) {
            if ('' === trim($declaration)) {
                continue;
            }

            $parts = explode(':', $declaration, 2);

            if (2 !== \count($parts) || !\in_array(trim($parts[0]), $allowed, true)) {
                $invalid();
            }

            $value = preg_replace('/url\(#[\p{L}_][\p{L}\p{N}_:.-]*\)/u', '', trim($parts[1]));

            if (!preg_match('/^[\w\s#.,%()+-]*$/uD', $value) || preg_match('/url|expression|var\s*\(/i', $value)) {
                $invalid();
            }
        }
    }

    private function isSvglUrl(mixed $url): bool
    {
        return \is_string($url) && 1 === preg_match('~^https://svgl\.app/(?:library/)?[a-zA-Z0-9_-]+\.svg$~D', $url);
    }

    private function download(string $url): string
    {
        return Cache::remember('admin.svgl.svg.v1.' . sha1($url), self::CACHE_TTL, fn (): string => $this->fetchSvg($url));
    }

    private function fetchSvg(string $url): string
    {
        try {
            $response = Http::connectTimeout(3)->timeout(10)->withOptions([
                'allow_redirects' => false,
                'progress' => static function ($total, $downloaded): void {
                    if (self::MAX_BYTES < $total || self::MAX_BYTES < $downloaded) {
                        throw new \RuntimeException('Provider icon exceeds size limit.');
                    }
                },
            ])->get($url)->throw();
            abort_unless($response->successful(), 502);
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, __('admin.icons.unavailable'));
        }

        return $this->validateSvg($response->body());
    }
}
