<?php
declare(strict_types=1);


namespace App\Services\Announcements\Values;


/**
 * The usage policy a user has to accept, resolved for one concrete request.
 *
 * `$hash` identifies the revision of `$text` (see
 * {@see \App\Services\Announcements\PolicyContentHasher}). The client sends it back when
 * completing the registration, which is what lets the server notice that the policy was edited
 * while the user was reading it.
 */
readonly class RegistrationPolicy
{
    public function __construct(
        /** Id of the underlying announcement. */
        public int    $id,
        /** Locale the text was resolved for, e.g. `de_DE`. */
        public string $locale,
        public string $text,
        /** Hash of the normalized `$text`. */
        public string $hash
    )
    {
    }

    /**
     * The shape served as the `policy` attribute of the `registrations` JSON:API resource —
     * the only consumer. `id` is a string on purpose: the attribute is stable API surface
     * and the SPA schema types it as a coerced string.
     *
     * @return array{id: string, locale: string, text: string, hash: string}
     */
    public function toArray(): array
    {
        return [
            'id' => (string)$this->id,
            'locale' => $this->locale,
            'text' => $this->text,
            'hash' => $this->hash,
        ];
    }
}
