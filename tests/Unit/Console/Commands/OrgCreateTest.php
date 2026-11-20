<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Admin\OrgCreate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OrgCreate::class)]
class OrgCreateTest extends TestCase
{
    use RefreshDatabase;

    public function testItCreatesOrganizationWithoutUsers(): void
    {
        $this->artisan('org:create', ['name' => 'Test Uni'])->assertExitCode(0);

        $organization = Organization::query()->where('name', 'Test Uni')->sole();
        self::assertCount(0, $organization->users);
    }

    public function testItAttachesAdminsAndMembers(): void
    {
        $admin = User::factory()->create(['username' => 'tester']);
        $member = User::factory()->create(['username' => 'someone']);

        $this->artisan('org:create', [
            'name' => 'Test Uni',
            '--admins' => ['tester'],
            '--members' => ['someone'],
        ])->assertExitCode(0);

        $organization = Organization::query()->where('name', 'Test Uni')->sole();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
    }

    public function testItFailsAtomicallyOnUnknownUsername(): void
    {
        $tester = User::factory()->create(['username' => 'tester']);

        $this->artisan('org:create', [
            'name' => 'Test Uni',
            '--admins' => ['tester', 'missing'],
        ])->assertExitCode(1);

        // Nothing was written: no "Test Uni" organization, and no membership
        // for the known user either. (The migration seeds a "HAWKI" user as
        // member of the "HAWKI" organization, so the table is not empty.)
        self::assertSame(0, Organization::query()->where('name', 'Test Uni')->count());
        self::assertSame(
            0,
            DB::table('organization_user')->where('user_id', $tester->id)->count(),
        );
    }

    public function testItRejectsDuplicateName(): void
    {
        Organization::create(['name' => 'Test Uni']);

        $this->artisan('org:create', ['name' => 'Test Uni'])->assertExitCode(1);

        self::assertSame(1, Organization::query()->where('name', 'Test Uni')->count());
    }
}
