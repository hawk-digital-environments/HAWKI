<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\LocalAuthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LocalAuthService::class)]
class LocalAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('employeetype');
            $table->string('local_password')->nullable();
            $table->boolean('isRemoved')->default(false);
        });
    }

    public function testLocalAccountCanSignInWithUsernameOrEmail(): void
    {
        DB::table('users')->insert([
            'name' => 'Local User',
            'username' => 'local-user',
            'email' => 'local@example.test',
            'employeetype' => 'guest',
            'local_password' => Hash::make('correct horse battery staple'),
        ]);

        foreach (['local-user', 'local@example.test'] as $account) {
            $service = new LocalAuthService();
            $service->useCredentials($account, 'correct horse battery staple');
            $info = $service->authenticate(request());
            self::assertSame('local-user', $info->username);
            self::assertSame('Local User', $info->displayName);
        }
    }

    public function testExternalRemovedAndWrongPasswordAccountsAreRejected(): void
    {
        DB::table('users')->insert([
            ['name' => 'External', 'username' => 'external', 'email' => 'external@example.test', 'employeetype' => 'staff', 'local_password' => null, 'isRemoved' => false],
            ['name' => 'Removed', 'username' => 'removed', 'email' => 'removed@example.test', 'employeetype' => 'staff', 'local_password' => Hash::make('correct horse battery staple'), 'isRemoved' => true],
            ['name' => 'Local', 'username' => 'local', 'email' => 'local@example.test', 'employeetype' => 'staff', 'local_password' => Hash::make('correct horse battery staple'), 'isRemoved' => false],
        ]);

        foreach ([['external', 'anything'], ['removed', 'correct horse battery staple'], ['local', 'wrong password'], ['missing', 'anything']] as [$account, $password]) {
            $service = new LocalAuthService();
            $service->useCredentials($account, $password);
            try {
                $service->authenticate(request());
                self::fail('Invalid local credentials were accepted.');
            } catch (AuthFailedException $exception) {
                self::assertSame(401, $exception->getCode());
            }
        }
    }
}
