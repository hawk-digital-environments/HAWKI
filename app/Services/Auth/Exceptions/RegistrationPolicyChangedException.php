<?php
declare(strict_types=1);

namespace App\Services\Auth\Exceptions;

use App\Services\Announcements\Values\RegistrationPolicy;

/**
 * The policy the registration request is completing against is not the policy that is
 * currently in effect — either its wording changed while the user was reading it, or no
 * valid consent is on record at all. Mapped to the stable `registration_policy_changed`
 * error code.
 */
class RegistrationPolicyChangedException extends \RuntimeException implements AuthExceptionInterface
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function becauseConsentIsMissing(RegistrationPolicy $currentPolicy): self
    {
        return new self(sprintf(
            'The registration carried no policy reference and no consent for the policy currently '
            . 'in effect (#%d) is on record. Show the current policy and have the user accept it '
            . 'before completing the registration.',
            $currentPolicy->id
        ));
    }

    public static function becauseTheLivePolicyDiffers(int $referencedId, RegistrationPolicy $currentPolicy): self
    {
        return new self(sprintf(
            'The registration referenced policy #%d, but policy #%d (hash %s) is currently in effect. '
            . 'The policy changed while the registration was open; reload the current policy and '
            . 'complete the registration again.',
            $referencedId,
            $currentPolicy->id,
            $currentPolicy->hash
        ));
    }
}
