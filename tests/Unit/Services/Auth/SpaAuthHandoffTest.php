<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\SpaAuthHandoff;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SpaAuthHandoff::class)]
class SpaAuthHandoffTest extends TestCase
{
    public function testFailureKeepsASanitizedNextForTheRetry(): void
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request = Request::create('/auth/redirect', 'GET');
        $request->setLaravelSession($session);
        $handoff = new SpaAuthHandoff();

        $handoff->start($request, '/new/chat/abc?tab=1');

        self::assertSame(
            '/new/auth/login?next=%2Fnew%2Fchat%2Fabc%3Ftab%3D1',
            $handoff->completeFailure($request, 'provider_failed')
        );
        self::assertSame('provider_failed', $handoff->pullLastError($request));
        self::assertNull($handoff->pullLastError($request));
    }
}
