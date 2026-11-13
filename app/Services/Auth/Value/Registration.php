<?php
declare(strict_types=1);

namespace App\Services\Auth\Value;

use App\Services\Announcements\Values\RegistrationPolicy;

/**
 * The registration resource behind JSON:API `registrations` — the singleton `hawki` that
 * describes the registration in progress: which policy still needs consent, or that the
 * consent already covers the current policy revision.
 */
readonly class Registration
{
    public function __construct(
        public string              $id,
        public ?RegistrationPolicy $policy,
        public string              $consent,
    )
    {
    }
}
