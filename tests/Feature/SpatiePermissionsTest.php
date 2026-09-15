<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConv;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\EmployeeTypeRoleSyncer;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Admin\Repositories\RoleRepository;
use App\Services\Admin\RoleAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Spatie\Permission\Models\Permission as StoredPermission;
use Tests\TestCase;

#[CoversNothing()]
class SpatiePermissionsTest extends TestCase
{
    use DatabaseTransactions;

    public function testManualAndMappedSourcesProjectToOneMembership(): void
    {
        $user = User::factory()->create(['employeetype' => 'overlap']);
        $role = $this->role(['usage.view']);
        DB::table('employee_type_role_mappings')->insert(['employee_type' => 'overlap', 'role_id' => $role->id]);
        $assignments = app(RoleAssignmentService::class);
        $assignments->replace($user, [$role->id]);
        app(EmployeeTypeRoleSyncer::class)->sync($user);
        self::assertSame(2, DB::table('role_user')->where('user_id', $user->id)->count());
        self::assertSame(1, $user->roles()->count());

        $assignments->replace($user, []);
        self::assertTrue($user->can('usage.view'));
        $assignments->replace($user, [$role->id]);
        $user->forceFill(['employeetype' => 'unmapped'])->save();
        app(EmployeeTypeRoleSyncer::class)->sync($user);
        self::assertTrue($user->can('usage.view'));
        self::assertSame(['manual'], DB::table('role_user')->where('user_id', $user->id)->pluck('source')->all());

        $user->load('roles.permissions', 'permissions');
        $assignments->replace($user, []);
        self::assertFalse($user->can('usage.view'));
        self::assertFalse($user->hasPermissionTo('usage.view'));
        self::assertSame([], app(PermissionService::class)->roleIds($user));
    }

    public function testRevocationAndDisabledAccountsInvalidateAlreadyLoadedUsers(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['usage.view']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $user->load('roles.permissions', 'permissions');
        self::assertTrue($user->hasPermissionTo('usage.view'));
        self::assertTrue($user->can('usage.view'));
        // These writes bypass RoleGuard::mutate(), which is what invalidates the per-request memo.
        $role->syncPermissions([]);
        app(PermissionService::class)->forget();
        self::assertFalse($user->hasPermissionTo('usage.view'));
        self::assertFalse($user->can('usage.view'));
        $role->syncPermissions(['usage.view']);
        app(PermissionService::class)->forget();

        foreach (['admin_disabled', 'isRemoved'] as $column) {
            DB::table('users')->where('id', $user->id)->update([$column => true]);
            app(PermissionService::class)->forget();
            self::assertFalse($user->hasPermissionTo('usage.view'));
            self::assertFalse($user->can('usage.view'));
            self::assertSame(['usage.view'], app(PermissionService::class)->assignedPermissionsOf($user));
            DB::table('users')->where('id', $user->id)->update([$column => false]);
            app(PermissionService::class)->forget();
            self::assertTrue($user->can('usage.view'));
        }
    }

    public function testRetiredAndDirectPermissionsCannotGrantAccessOrBypassPolicies(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['usage.view']);

        foreach (['retired.permission', 'update'] as $name) {
            $role->givePermissionTo(StoredPermission::create(['name' => $name, 'guard_name' => 'web']));
        }

        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $user->givePermissionTo('admin.access');
        self::assertSame(['usage.view'], app(PermissionService::class)->permissionsOf($user));
        self::assertFalse($user->can('admin.access'));
        self::assertFalse($user->hasPermissionTo('admin.access'));
        self::assertFalse($user->can('retired.permission'));
        self::assertFalse($user->can('update', new AiConv(['user_id' => $user->id + 1])));
        self::assertTrue($user->can('update', new AiConv(['user_id' => $user->id])));
    }

    public function testAdministratorGrantsDoNotBypassConversationOwnership(): void
    {
        $user = User::factory()->create();
        $role = $this->role(Permission::values());
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        self::assertTrue(Gate::forUser($user)->allows('roles.manage'));
        self::assertFalse(Gate::forUser($user)->allows('update', new AiConv(['user_id' => $user->id + 1])));
        self::assertTrue(Gate::forUser($user)->allows('update', new AiConv(['user_id' => $user->id])));
        self::assertFalse($user->hasPermissionTo('roles.manage', 'sanctum'));
    }

    public function testSanctumBearerAuthenticationUsesTheWebPermissionGuard(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['admin.access', 'roles.manage']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $token = $user->createToken('spatie-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/hawki/v1/admin-roles', ['Accept' => 'application/vnd.api+json'])
            ->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function testFailedLastAdministratorRevocationRollsBackSourcesAndEffectiveMembership(): void
    {
        $this->removeExistingAssignments();
        $user = User::factory()->create();
        $role = $this->role(['admin.access', 'roles.manage']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $user->load('roles.permissions');

        try {
            app(RoleAssignmentService::class)->replace($user, []);
            self::fail('Removing the last administrator must fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('roles', $exception->errors());
        }

        self::assertTrue($user->can('roles.manage'));
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $role->id, 'source' => 'manual']);
        self::assertSame([$role->id], app(PermissionService::class)->roleIds($user));
    }

    public function testLastAdministratorCanHoldRequiredPermissionsAcrossRoles(): void
    {
        $this->removeExistingAssignments();
        $user = User::factory()->create();
        $access = $this->role(['admin.access']);
        $manage = $this->role(['roles.manage']);
        app(RoleAssignmentService::class)->replace($user, [$access->id, $manage->id]);
        $this->expectException(ValidationException::class);
        app(RoleAssignmentService::class)->replace($user, [$access->id]);
    }

    public function testAnAtomicTransferCanTemporarilyRemoveTheLastAdministrator(): void
    {
        $this->removeExistingAssignments();
        $previous = User::factory()->create();
        $next = User::factory()->create();
        $role = $this->role(['admin.access', 'roles.manage']);
        app(RoleAssignmentService::class)->replace($previous, [$role->id]);

        app(\App\Services\Admin\RoleGuard::class)->mutate(static function () use ($previous, $next, $role): void {
            app(RoleAssignmentService::class)->replace($previous, []);
            app(RoleAssignmentService::class)->replace($next, [$role->id]);
        });

        self::assertFalse($previous->can('roles.manage'));
        self::assertTrue($next->can('roles.manage'));
    }

    public function testFailedRoleEditDoesNotLeaveCachedGrants(): void
    {
        $this->removeExistingAssignments();
        $user = User::factory()->create();
        $role = $this->role(['admin.access', 'roles.manage']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $user->load('roles.permissions');

        try {
            app(RoleRepository::class)->save($role->id, [
                'slug' => $role->name, 'name' => 'Changed', 'permissions' => ['admin.access'],
            ], $user);
            self::fail('The last administrator must retain roles.manage.');
        } catch (ValidationException) {
            self::assertTrue($user->hasPermissionTo('roles.manage'));
            self::assertSame('Test role', $role->fresh()->display_name);
        }
    }

    public function testBootstrapCommandPreservesMappedAssignmentsAndAuditsChanges(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['usage.view']);
        app(RoleAssignmentService::class)->replace($user, [$role->id], 'employeetype');
        $this->artisan('rbac:grant', ['username' => $user->username, 'role' => $role->name])->assertSuccessful();
        $this->artisan('rbac:grant', ['username' => $user->username, 'role' => $role->name, '--revoke' => true])->assertSuccessful();
        self::assertTrue($user->can('usage.view'));
        self::assertSame(['employeetype'], DB::table('role_user')->where('user_id', $user->id)->pluck('source')->all());
        $this->assertDatabaseHas('admin_audit_log', ['action' => 'revoke', 'resource_type' => 'roles', 'resource_id' => $role->id]);
    }

    public function testRepeatedChecksForOneUserResolveGrantsOnlyOnce(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['admin.access', 'usage.view']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $permissions = app(PermissionService::class);

        DB::enableQueryLog();

        try {
            DB::flushQueryLog();
            self::assertTrue($permissions->has($user, Permission::ACCESS));
            self::assertGreaterThan(0, \count(DB::getQueryLog()), 'The first check must read the grants.');

            DB::flushQueryLog();
            self::assertTrue($permissions->has($user, 'usage.view'));
            self::assertFalse($permissions->has($user, Permission::ROLES_MANAGE));
            $permissions->authorize($user, 'usage.view');
            self::assertSame(['admin.access', 'usage.view'], $permissions->permissionsOf($user));
            self::assertTrue($user->can('usage.view'));
            self::assertSame([], DB::getQueryLog(), 'Repeated checks must reuse the memoized resolution.');
        } finally {
            DB::disableQueryLog();
        }
    }

    public function testGrantChangeThroughTheGuardIsVisibleWithoutANewServiceInstance(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['usage.view']);
        $permissions = app(PermissionService::class);
        self::assertFalse($permissions->has($user, 'usage.view'));

        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        self::assertSame($permissions, app(PermissionService::class), 'The service is bound per request.');
        self::assertTrue($permissions->has($user, 'usage.view'));

        app(RoleAssignmentService::class)->replace($user, []);
        self::assertFalse($permissions->has($user, 'usage.view'));
    }

    public function testForgetClearsMemoizedGrantsAndEligibility(): void
    {
        $user = User::factory()->create();
        $role = $this->role(['usage.view']);
        app(RoleAssignmentService::class)->replace($user, [$role->id]);
        $permissions = app(PermissionService::class);
        self::assertTrue($permissions->has($user, 'usage.view'));

        // A direct Spatie write bypasses the guard and therefore the invalidation.
        $role->syncPermissions([]);
        self::assertTrue($permissions->has($user, 'usage.view'));
        $permissions->forget($user->id);
        self::assertFalse($permissions->has($user, 'usage.view'));

        $role->syncPermissions(['usage.view']);
        $permissions->forget();
        self::assertTrue($permissions->has($user, 'usage.view'));

        DB::table('users')->where('id', $user->id)->update(['admin_disabled' => true]);
        self::assertTrue($permissions->isEligible($user));
        $permissions->forget();
        self::assertFalse($permissions->isEligible($user));
        self::assertSame([], $permissions->permissionsOf($user));
        self::assertSame(['usage.view'], $permissions->assignedPermissionsOf($user));
    }

    private function role(array $permissions): Role
    {
        $role = Role::create(['name' => 'spatie-' . fake()->uuid(), 'display_name' => 'Test role']);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function removeExistingAssignments(): void
    {
        DB::table('role_user')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('employee_type_role_mappings')->delete();
    }
}
