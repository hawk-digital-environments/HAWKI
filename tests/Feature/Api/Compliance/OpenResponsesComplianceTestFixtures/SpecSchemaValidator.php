<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Compliance\OpenResponsesComplianceTestFixtures;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates response payloads against the published Open Responses OpenAPI document
 * (`research/openresponses/public/openapi/openapi.json`) — the PHP counterpart of the
 * compliance runner's generated Zod schemas.
 *
 * Component schemas reference each other via `#/{components}/schemas/…` pointers, so
 * before validation every internal reference is recursively inlined (draft-07
 * semantics: a `$ref` object replaces its position wholesale). The result is a
 * self-contained schema per component, cached after first resolution.
 */
final class SpecSchemaValidator
{
    private const string SPEC_PATH = 'research/openresponses/public/openapi/openapi.json';

    /**
     * @var null|array<string, mixed>
     */
    private static ?array $components = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $dereferencedSchemas = [];

    /**
     * Validates $data against the named component schema.
     *
     * @return list<string> empty when valid; human-readable violation messages otherwise
     */
    public static function validate(array $data, string $schemaName): array
    {
        $payload = json_decode((string) json_encode($data), false);
        $schema = json_decode((string) json_encode(self::dereferencedSchema($schemaName)), false);

        $result = (new Validator())->validate($payload, $schema);

        if ($result->isValid()) {
            return [];
        }

        $formatted = (new ErrorFormatter())->format($result->error());

        $messages = [];

        foreach ($formatted as $path => $errors) {
            foreach ($errors as $error) {
                $messages[] = ('' === $path ? '(root)' : $path) . ': ' . $error;
            }
        }

        return $messages;
    }

    /**
     * Validates one parsed SSE event payload against the streaming-event union: the
     * event's own schema is selected by its `type` discriminator (mapped from the spec's
     * own component declarations), so every known event type validates against its
     * strict schema and unknown types fail loudly.
     *
     * @param array<string, mixed> $eventData
     *
     * @return list<string>
     */
    public static function validateStreamEvent(array $eventData): array
    {
        $type = $eventData['type'] ?? null;

        if (!\is_string($type) || '' === $type) {
            return ['(root): streaming events must carry a non-empty string "type" discriminator'];
        }

        $schemaName = self::eventSchemaName($type);

        if (null === $schemaName) {
            return ['(root): unknown streaming event type "' . $type . '"'];
        }

        try {
            return self::validate($eventData, $schemaName);
        } catch (\Throwable $e) {
            return ['(root): schema "' . $schemaName . '" could not be applied: ' . $e->getMessage()];
        }
    }

    /**
     * Returns the named component schema with every internal `#/components/schemas/…`
     * reference recursively inlined, so the validator never has to resolve pointers.
     * Draft-07 semantics: a `$ref` object is replaced wholesale (siblings are ignored).
     *
     * @return array<string, mixed>
     */
    private static function dereferencedSchema(string $schemaName, int $depth = 0): array
    {
        if (0 === $depth && isset(self::$dereferencedSchemas[$schemaName])) {
            return self::$dereferencedSchemas[$schemaName];
        }

        if (32 < $depth) {
            return [];
        }

        $schema = self::components()['schemas'][$schemaName] ?? [];

        foreach ($schema as $key => $value) {
            if ('$ref' === $key && \is_string($value) && str_starts_with($value, '#/components/schemas/')) {
                return self::dereferencedSchema(mb_substr($value, mb_strlen('#/components/schemas/')), $depth + 1);
            }

            if (\is_array($value)) {
                $schema[$key] = self::dereferenceValue($value, $depth);
            }
        }

        if (0 === $depth) {
            self::$dereferencedSchemas[$schemaName] = $schema;
        }

        return $schema;
    }

    /**
     * @param array<int|string, mixed> $value
     *
     * @return array<int|string, mixed>
     */
    private static function dereferenceValue(array $value, int $depth): array
    {
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::dereferenceValue($item, $depth + 1);

                continue;
            }

            if ('$ref' === $key && \is_string($item) && str_starts_with($item, '#/components/schemas/')) {
                $value = self::dereferencedSchema(mb_substr($item, mb_strlen('#/components/schemas/')), $depth + 1);

                continue;
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function components(): array
    {
        if (null === self::$components) {
            $document = json_decode((string) file_get_contents(self::specPath()), true);

            self::$components = $document['components'] ?? [];
        }

        return self::$components;
    }

    private static function specPath(): string
    {
        return \dirname(__DIR__, 5) . '/' . self::SPEC_PATH;
    }

    /**
     * Maps a wire event type to its component schema by reading each streaming-event
     * schema's `type` discriminator out of the spec — no naming conventions.
     */
    private static function eventSchemaName(string $eventType): ?string
    {
        static $map = null;

        if (null === $map) {
            $map = [];

            foreach (self::components()['schemas'] ?? [] as $name => $schema) {
                if (!\is_array($schema) || !str_ends_with((string) $name, 'StreamingEvent')) {
                    continue;
                }

                $type = $schema['properties']['type']['const'] ?? null;

                if (\is_string($type)) {
                    $map[$type] = (string) $name;
                } elseif (\is_array($schema['properties']['type']['enum'] ?? null)) {
                    foreach ($schema['properties']['type']['enum'] as $allowed) {
                        if (\is_string($allowed)) {
                            $map[$allowed] = (string) $name;
                        }
                    }
                }
            }
        }

        return $map[$eventType] ?? null;
    }
}
