<?php

declare(strict_types=1);

namespace App\Services\Ai\Extensions;

use Illuminate\Container\Attributes\Singleton;

/**
 * The registry of `hawkiExtensions` fields the API accepts — the
 * plugin-style extension point for request payloads: slices declare their
 * own extension keys (validation rules per envelope location, plus
 * messages), and the generic validation sites merge them mechanically.
 * The Ai core never names a slice's fields.
 *
 * Wired like {@see \App\Services\Ai\Agents\AgentRegistry}: a singleton
 * whose declarations are added by service providers via
 * `$app->extend(HawkiExtensionsRegistry::class, ...)`.
 *
 * Usage (service provider):
 * ```php
 * $this->app->extend(
 *     HawkiExtensionsRegistry::class,
 *     fn(HawkiExtensionsRegistry $registry): HawkiExtensionsRegistry => $registry
 *         ->declare('assistant_handle', [
 *             'payload.hawkiExtensions' => ['nullable', 'string', 'exists:assistants,handle'],
 *             'payload.messages.*.hawkiExtensions' => ['nullable', 'string', 'max:255'],
 *         ])
 * );
 * ```
 *
 * Usage (validation site):
 * ```php
 * $request->validate([
 *     ...,
 *     ...$extensions->rulesFor('payload.hawkiExtensions', 'payload.messages.*.hawkiExtensions'),
 * ], $extensions->messagesFor('payload.hawkiExtensions', 'payload.messages.*.hawkiExtensions'));
 * ```
 */
#[Singleton]
final class HawkiExtensionsRegistry
{
    /** @var array<string, array<string, list<string>>> field key => envelope prefix => rules */
    private array $rulesByField = [];

    /** @var array<string, string> fully-qualified rule key => message */
    private array $messages = [];

    /**
     * Declares one extension field: the validation rules for every envelope
     * location it may appear in, keyed by the location's validation prefix
     * (e.g. `payload.hawkiExtensions`). Declaring the same field again is
     * safe and merges over the earlier declaration.
     *
     * @param array<string, list<string>> $rulesByPrefix
     * @param array<string, string> $messages Fully-qualified rule keys (prefix + field) to messages.
     */
    public function declare(string $field, array $rulesByPrefix, array $messages = []): self
    {
        $this->rulesByField[$field] = array_merge($this->rulesByField[$field] ?? [], $rulesByPrefix);
        $this->messages = array_merge($this->messages, $messages);

        return $this;
    }

    /**
     * The validation rules of every declared field for the given envelope
     * prefixes — merge this into the validation site's rules array.
     *
     * @param list<string> ...$prefixes
     * @return array<string, list<string>>
     */
    public function rulesFor(string ...$prefixes): array
    {
        $rules = [];

        foreach ($prefixes as $prefix) {
            foreach ($this->rulesByField as $field => $byPrefix) {
                if (isset($byPrefix[$prefix])) {
                    $rules[$prefix . '.' . $field] = $byPrefix[$prefix];
                }
            }
        }

        return $rules;
    }

    /**
     * The custom validation messages for rules of the given envelope
     * prefixes.
     *
     * @param list<string> ...$prefixes
     * @return array<string, string>
     */
    public function messagesFor(string ...$prefixes): array
    {
        $prefixKeys = [];

        foreach ($prefixes as $prefix) {
            foreach ($this->rulesByField as $field => $byPrefix) {
                if (isset($byPrefix[$prefix])) {
                    $prefixKeys[$prefix . '.' . $field] = true;
                }
            }
        }

        return array_intersect_key($this->messages, $prefixKeys);
    }
}
