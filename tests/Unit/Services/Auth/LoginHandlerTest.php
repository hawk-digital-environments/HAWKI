<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\LoginHandler;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Services\Auth\Value\LoginNextStep;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[CoversClass(LoginHandler::class)]
class LoginHandlerTest extends TestCase
{
    public function testKnownIdentityReplacesThePreviouslyAuthenticatedUser(): void
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request = Request::create('/req/login', 'POST');
        $request->setLaravelSession($session);

        $auth = $this->app->make(AuthManager::class);
        $auth->guard()->login($this->user(1, 'old-user'));
        $newUser = $this->user(2, 'new-user');

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneByUsername')->with('new-user')->willReturn($newUser);

        $result = $this->handler($auth, $repository, 'new-user')->handle($request);

        self::assertSame(LoginNextStep::HANDSHAKE, $result->nextStep);
        self::assertSame(2, $auth->guard()->id());
        self::assertFalse($session->has('registration_access'));
        self::assertFalse($session->has('authenticatedUserInfo'));
        self::assertFalse($session->has('auth.registration_ui'));
    }

    public function testNewIdentityReplacesTheOldGuardAndRegistrationSession(): void
    {
        $sessionHandler = new class(60) extends ArraySessionHandler {
            /** @var list<string> */
            public array $destroyedSessionIds = [];

            public function destroy($sessionId): bool
            {
                $this->destroyedSessionIds[] = $sessionId;

                return parent::destroy($sessionId);
            }
        };
        $session = new Store('test', $sessionHandler);
        $session->start();
        $session->put([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode(['username' => 'stale']),
            'auth.registration_ui' => 'new',
        ]);
        $oldSessionId = $session->getId();

        $request = Request::create('/req/login', 'POST');
        $request->setLaravelSession($session);

        $auth = $this->app->make(AuthManager::class);
        $auth->guard()->login($this->user(1, 'old-user'));

        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())
            ->method('findOneByUsername')
            ->with('new-user')
            ->willReturn(null);

        $result = $this->handler($auth, $repository, 'new-user')->handle($request);

        self::assertSame(LoginNextStep::REGISTER, $result->nextStep);
        self::assertFalse($auth->guard()->check());
        self::assertNotSame($oldSessionId, $session->getId());
        self::assertContains($oldSessionId, $sessionHandler->destroyedSessionIds);
        self::assertTrue($session->get('registration_access'));
        self::assertSame('new-user', json_decode($session->get('authenticatedUserInfo'), true)['username']);
        self::assertFalse($session->has('auth.registration_ui'));
    }

    public function testRemovedIdentityFallsThroughToRegistrationInsteadOfReusingTheOldGuard(): void
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request = Request::create('/req/login', 'POST');
        $request->setLaravelSession($session);

        $auth = $this->app->make(AuthManager::class);
        $auth->guard()->login($this->user(1, 'old-user'));

        $repository = $this->createMock(UserRepository::class);
        // The repository returns null for a removed identity because its active scope remains on.
        $repository->expects(self::once())->method('findOneByUsername')->with('removed-user')->willReturn(null);

        $result = $this->handler($auth, $repository, 'removed-user')->handle($request);

        self::assertSame(LoginNextStep::REGISTER, $result->nextStep);
        self::assertFalse($auth->guard()->check());
        self::assertSame('removed-user', json_decode($session->get('authenticatedUserInfo'), true)['username']);
    }

    private function handler(AuthManager $auth, UserRepository $repository, string $username): LoginHandler
    {
        $service = new class($username) implements AuthServiceInterface {
            public function __construct(private readonly string $username)
            {
            }

            public function authenticate(Request $request): AuthenticatedUserInfo
            {
                return new AuthenticatedUserInfo($this->username, 'New User', 'new@example.test', 'employee');
            }
        };

        return new LoginHandler($service, $auth, new NullLogger(), $repository);
    }

    private function user(int $id, string $username): User
    {
        $user = new User();
        $user->setRawAttributes(['id' => $id, 'username' => $username]);

        return $user;
    }
}
