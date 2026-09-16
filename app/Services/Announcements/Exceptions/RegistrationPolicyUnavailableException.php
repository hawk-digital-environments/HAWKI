<?php
declare(strict_types=1);


namespace App\Services\Announcements\Exceptions;


use RuntimeException;

/**
 * Thrown when a policy announcement is in effect but its text cannot be delivered.
 *
 * This is a misconfiguration, not a user error: somebody published a policy without content
 * files. Registration has to stop — letting a user "accept" an empty policy would record a
 * consent to nothing — and the operator has to fix the installation, which is why the HTTP layer
 * answers `503` rather than a `4xx`.
 */
class RegistrationPolicyUnavailableException extends RuntimeException implements AnnouncementExceptionInterface
{
    public static function forMissingPolicy(): self
    {
        return new self('No active global registration policy is published.');
    }

    public static function forMissingContent(int $announcementId, string $view): self
    {
        return new self(sprintf(
            'Policy announcement %d has no content for the current locale and no default-locale ' .
            'fallback. Expected a markdown file in "resources/announcements/%s/".',
            $announcementId,
            $view
        ));
    }

    public static function forEmptyContent(int $announcementId, string $view, string $locale): self
    {
        return new self(sprintf(
            'The content file of policy announcement %d ("resources/announcements/%s/%s.md") is empty.',
            $announcementId,
            $view,
            $locale
        ));
    }
}
