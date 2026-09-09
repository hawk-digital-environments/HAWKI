<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Users\Keychain;

use App\Models\User;
use App\Services\Users\Keychain\KeychainStateResolver;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use App\Services\Users\Keychain\Value\KeychainState;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(KeychainStateResolver::class)]
class KeychainStateResolverTest extends TestCase
{
    public function testItRequiresEveryCanonicalCorePairAndAPublicKeyToInitialize(): void
    {
        self::assertSame(KeychainState::INITIALIZED, $this->resolve([
            ['key' => 'privateKey', 'type' => 'private_key'],
            ['key' => 'publicKey', 'type' => 'public_key'],
            ['key' => 'aiConvKey', 'type' => 'ai_conv'],
        ], 'public-key'));
    }

    public function testItOnlyRecognizesLegacyMigrationWhenTheMigrationBlobExistsAndCoreEntriesAreAbsent(): void
    {
        self::assertSame(KeychainState::LEGACY_MIGRATION_REQUIRED, $this->resolve([
            ['key' => 'room-1', 'type' => 'room_key'],
        ], '', true));

        self::assertSame(KeychainState::INCONSISTENT, $this->resolve([
            ['key' => 'privateKey', 'type' => 'room_key'],
        ], '', true));

        self::assertSame(KeychainState::SETUP_REQUIRED, $this->resolve([], '', false));
    }

    public function testItClassifiesPartialAndNonemptyKeychainsAsInconsistent(): void
    {
        self::assertSame(KeychainState::INCONSISTENT, $this->resolve([
            ['key' => 'privateKey', 'type' => 'private_key'],
            ['key' => 'publicKey', 'type' => 'public_key'],
        ], 'public-key'));

        self::assertSame(KeychainState::INCONSISTENT, $this->resolve([
            ['key' => 'room-1', 'type' => 'room_key'],
        ], ''));
    }

    /**
     * @param list<array{key: string, type: string}> $entries
     */
    private function resolve(array $entries, string $publicKey, bool $hasLegacyMigrationBlob = false): KeychainState
    {
        $repository = $this->createMock(UserKeychainRepository::class);
        $repository->method('findAllKeysAndTypesOfUser')->willReturn($entries);

        $user = new User();
        $user->publicKey = $publicKey;

        return (new KeychainStateResolver($repository))->resolveForUser($user, $hasLegacyMigrationBlob);
    }
}
