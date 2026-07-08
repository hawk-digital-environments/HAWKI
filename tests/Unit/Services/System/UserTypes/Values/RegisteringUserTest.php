<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\UserTypes\Values;

use App\Services\System\UserTypes\Values\RegisteringUser;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RegisteringUser::class)]
class RegisteringUserTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');

        static::assertInstanceOf(RegisteringUser::class, $sut);
    }

    public function testItExposesUsername(): void
    {
        $sut = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');

        static::assertSame('jdoe', $sut->username);
    }

    public function testItExposesName(): void
    {
        $sut = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');

        static::assertSame('John Doe', $sut->name);
    }

    public function testItExposesEmail(): void
    {
        $sut = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');

        static::assertSame('jdoe@example.com', $sut->email);
    }

    public function testItExposesEmployeeType(): void
    {
        $sut = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');

        static::assertSame('staff', $sut->employeeType);
    }
}
