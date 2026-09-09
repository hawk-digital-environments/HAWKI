<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\RegistrationGuard;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(RegistrationGuard::class)]
class RegistrationGuardTest extends TestCase
{
    public function testInitializationRequiresAnActiveAccountWithAPublicKey(): void
    {
        self::assertFalse(RegistrationGuard::isAccountInitialized(null));
        $user = new User(['publicKey' => '', 'isRemoved' => false]);
        self::assertFalse(RegistrationGuard::isAccountInitialized($user));
        $user->publicKey = 'public-key';
        self::assertTrue(RegistrationGuard::isAccountInitialized($user));
        $user->isRemoved = true;
        self::assertFalse(RegistrationGuard::isAccountInitialized($user));
    }

    #[DataProvider('collisions')]
    public function testUsernameCollisionsRetryOnce(string $state): void
    {
        $calls = 0;
        $exception = $this->queryException($state, 'users.username');
        $result = RegistrationGuard::retryUsernameCollision(function () use (&$calls, $exception): string {
            if (++$calls === 1) {
                throw $exception;
            }
            return 'completed';
        });
        self::assertSame('completed', $result);
        self::assertSame(2, $calls);
    }

    public static function collisions(): array
    {
        return [['23000'], ['23505']];
    }

    public function testSecondCollisionEscapesWithoutAnotherRetry(): void
    {
        $this->assertAttempts($this->queryException('23000', 'users.username'), 2);
    }

    public function testOtherConstraintsAndDatabaseFailuresAreNotRetried(): void
    {
        $this->assertAttempts($this->queryException('23000', 'users.email'), 1);
        $this->assertAttempts($this->queryException('08006', 'users.username'), 1);
    }

    private function assertAttempts(QueryException $exception, int $expected): void
    {
        $calls = 0;
        try {
            RegistrationGuard::retryUsernameCollision(function () use (&$calls, $exception): never {
                $calls++;
                throw $exception;
            });
            self::fail('The database failure must escape.');
        } catch (QueryException $caught) {
            self::assertSame($exception, $caught);
            self::assertSame($expected, $calls);
        }
    }

    private function queryException(string $state, string $constraint): QueryException
    {
        $cause = new PDOException('Constraint violation: ' . $constraint);
        $cause->errorInfo = [$state, 19, $cause->getMessage()];
        return new QueryException('sqlite', 'insert into users (username, email) values (?, ?)', [], $cause);
    }
}
