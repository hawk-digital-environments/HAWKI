<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


use App\Models\User;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Symfony\Component\HttpFoundation\Response;

/**
 * The outcome of a {@see \App\Services\Auth\LoginHandler::handle()} call.
 *
 * Exactly one of two shapes applies:
 *
 * - **A step to take** — {@see nextStep} is set. Authentication succeeded and the caller decides
 *   how to move the visitor on: the legacy gateway redirects, the JSON:API action reports the
 *   step as `meta.next`.
 * - **A ready-made response** — {@see response} is set. The auth service produced its own
 *   response (a redirect to an identity provider, or a post-processing redirect) and it has to
 *   reach the browser unchanged.
 *
 * Check with {@see isResponse()} before reading either field.
 */
readonly class LoginResult
{
    private function __construct(
        public LoginNextStep|null $nextStep,
        public Response|null      $response,
        public User|null          $user,
        public AuthenticatedUserInfo|null $authenticatedUserInfo
    )
    {
    }

    public static function forStep(
        LoginNextStep $nextStep,
        ?User $user = null,
        ?AuthenticatedUserInfo $authenticatedUserInfo = null
    ): self
    {
        return new self($nextStep, null, $user, $authenticatedUserInfo);
    }

    public static function forResponse(Response $response): self
    {
        return new self(null, $response, null, null);
    }

    /**
     * True when the auth service supplied its own response, which must be returned as-is.
     */
    public function isResponse(): bool
    {
        return $this->response !== null;
    }
}
