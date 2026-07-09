<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent\ProxyClientTestFixtures;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Extends PendingRequest to declare getSsrfSafe() as a real method,
 * making it mockable by PHPUnit without addMethods() (which is deprecated).
 */
class TestPendingRequest extends PendingRequest
{
    public function getSsrfSafe(string $url, mixed $query = null): Response
    {
        throw new \LogicException('getSsrfSafe() must be configured on the mock before calling.');
    }
}
