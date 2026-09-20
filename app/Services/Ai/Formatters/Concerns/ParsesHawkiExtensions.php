<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Concerns;

use App\Services\Ai\Chat\Values\AiRequest;

/**
 * Shared parsing of the top-level `hawki` request object — the wire convention for
 * HAWKI-specific request metadata (tool-transfer strings, attachment UUIDs, legacy
 * params, broadcast flag) that becomes {@see AiRequest::$hawkiExtensions}.
 *
 * Spec-legal for OpenAI-style superset wire formats: unknown top-level objects are
 * ignored by OpenAI-compatible clients. Used by every chat formatter that wants the
 * convention; see POC-HUB-AND-SPOKE.md §9.
 */
trait ParsesHawkiExtensions
{
    /**
     * @param array<string, mixed> $body
     *
     * @return null|array<string, mixed>
     */
    protected static function parseHawkiExtensions(array $body): ?array
    {
        $hawki = $body['hawki'] ?? null;

        if (!\is_array($hawki)) {
            return null;
        }

        $extensions = [];

        if (\is_array($hawki['tools'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_TOOLS] = array_values(array_filter(
                $hawki['tools'],
                static fn (mixed $tool): bool => \is_string($tool),
            ));
        }

        if (\is_array($hawki['attachments'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_ATTACHMENTS] = array_values(array_filter(
                $hawki['attachments'],
                static fn (mixed $uuid): bool => \is_string($uuid),
            ));
        }

        if (\is_array($hawki['params'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_PARAMS] = $hawki['params'];
        }

        if (true === ($hawki['broadcast'] ?? null)) {
            $extensions[AiRequest::HAWKI_EXTENSION_BROADCAST] = true;
        }

        return [] !== $extensions ? $extensions : null;
    }
}
