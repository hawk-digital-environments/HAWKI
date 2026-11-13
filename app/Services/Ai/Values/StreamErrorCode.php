<?php

declare(strict_types=1);

namespace App\Services\Ai\Values;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

/**
 * Stable wire codes for a failed generation stream. Clients map these to
 * translated messages: the provider could not be reached or answered with a
 * server error (`PROVIDER_UNREACHABLE`), it is overloaded or rate-limits us
 * (`PROVIDER_BUSY`), or anything else failed (`GENERATION_FAILED`).
 */
enum StreamErrorCode: string
{
    case PROVIDER_BUSY = 'PROVIDER_BUSY';
    case PROVIDER_UNREACHABLE = 'PROVIDER_UNREACHABLE';
    case GENERATION_FAILED = 'GENERATION_FAILED';

    /**
     * Classifies a failed stream by the first recognizable cause in the
     * exception chain, so exceptions wrapped by the agent layer still map to
     * the underlying provider problem.
     */
    public static function fromThrowable(\Throwable $e): self
    {
        do {
            $code = match (true) {
                $e instanceof RateLimitedException,
                $e instanceof ProviderOverloadedException => self::PROVIDER_BUSY,

                $e instanceof ConnectionException => self::PROVIDER_UNREACHABLE,

                $e instanceof RequestException => match (true) {
                    $e->response->tooManyRequests() => self::PROVIDER_BUSY,
                    $e->response->serverError() => self::PROVIDER_UNREACHABLE,
                    default => self::GENERATION_FAILED,
                },

                default => null,
            };

            if ($code !== null) {
                return $code;
            }
        } while ($e = $e->getPrevious());

        return self::GENERATION_FAILED;
    }
}
