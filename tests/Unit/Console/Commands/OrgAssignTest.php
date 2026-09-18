<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Admin\OrgAssign;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OrgAssign::class)]
class OrgAssignTest extends TestCase
{
    use RefreshDatabase;

    public function testItAttachesUserToSingleExistingOrganization(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        // The organizations migration seeds exactly one org ("HAWKI") into
        // the test database — that is the inference source here.
        $organization = Organization::query()->sole();

        $this->artisan('org:assign', ['username' => 'tester'])->assertExitCode(0);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function testItInfersOrganizationFromUsersOnlyMembership(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $organization = Organization::create(['name' => 'Infer Test Org']);
        $user->organizations()->attach($organization->id, ['role' => 'member']);
        // The seeded organization exists as well, so inference must come from
        // the user's membership rather than "the single existing org".
        self::assertGreaterThan(1, Organization::query()->count());

        $this->artisan('org:assign', ['username' => 'tester'])->assertExitCode(0);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function testItDemotesAdminToMemberByOrgName(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $organization = Organization::create(['name' => 'Demote Test Org']);
        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        $this->artisan('org:assign', [
            'username' => 'tester',
            '--org' => 'Demote Test Org',
            '--role' => 'member',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function testItResolvesOrganizationById(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $organization = Organization::create(['name' => 'Byid Test Org']);

        $this->artisan('org:assign', [
            'username' => 'tester',
            '--org' => (string) $organization->id,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function testItRemovesMembership(): void
    {
        $user = User::factory()->create(['username' => 'tester']);
        $organization = Organization::create(['name' => 'Remove Test Org']);
        $user->organizations()->attach($organization->id, ['role' => 'admin']);

        $this->artisan('org:assign', [
            'username' => 'tester',
            '--org' => 'Remove Test Org',
            '--remove' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
    }

    public function testItFailsWhenOrganizationCannotBeInferred(): void
    {
        User::factory()->create(['username' => 'tester']);
        // Seeded org plus one more: no single existing org to infer from.
        Organization::create(['name' => 'Ambiguity Test Org']);
        self::assertGreaterThan(1, Organization::query()->count());

        $this->artisan('org:assign', ['username' => 'tester'])->assertExitCode(1);
    }

    public function testItFailsForUnknownUser(): void
    {
        $this->artisan('org:assign', ['username' => 'missing'])->assertExitCode(1);
    }

    public function testItFailsForInvalidRole(): void
    {
        User::factory()->create(['username' => 'tester']);
        $organization = Organization::create(['name' => 'Invalid Test Org']);

        $this->artisan('org:assign', [
            'username' => 'tester',
            '--org' => 'Invalid Test Org',
            '--role' => 'owner',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
        ]);
    }
}
