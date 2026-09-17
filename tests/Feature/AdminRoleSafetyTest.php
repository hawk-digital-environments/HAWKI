<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\Repositories\RoleRepository;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

#[CoversNothing()]
class AdminRoleSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function testRoleUsedToRestrictAModelCannotBeDeletedWithoutUserMemberships(): void
    {
        $actor = $this->administrator();
        $restrictedRole = Role::create([
            'name' => 'model-only-role-' . $actor->id,
            'display_name' => 'Model only role',
            'guard_name' => 'web',
        ]);
        $provider = AiProvider::create([
            'provider_id' => 'role-delete-' . $actor->id,
            'name' => 'Role delete test',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://example.invalid',
            'api_key' => 'test-only',
        ]);
        $model = AiModel::create([
            'model_id' => 'role-delete-' . $actor->id,
            'label' => 'Role delete test',
            'provider_id' => $provider->id,
            'active' => true,
            'status' => OnlineStatus::ONLINE,
            'flags' => AiModelFlags::fromArray([]),
            'limits' => new NullAiModelLimits(),
            'pricing' => new NullPricing(),
            'settings' => AiModelSettings::fromArray([]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
        ]);
        $model->allowedRoles()->attach($restrictedRole->id, ['created_at' => now()]);

        try {
            app(RoleRepository::class)->delete($restrictedRole->id, $actor);
            self::fail('Expected a model access role to be protected from deletion.');
        } catch (ValidationException) {
            self::assertTrue($restrictedRole->fresh()->exists);
            self::assertTrue($model->allowedRoles()->whereKey($restrictedRole->id)->exists());
        }
    }

    public function testEditingOwnRoleCannotUseCachedGrantsToRemovePanelAccess(): void
    {
        $actor = $this->administrator();
        $this->administrator();
        $role = $actor->roles()->firstOrFail();

        try {
            app(RoleRepository::class)->save((int) $role->id, [
                'name' => $role->display_name,
                'slug' => $role->name,
                'description' => $role->description,
                'permissions' => [Permission::ROLES_MANAGE->value],
            ], $actor);
            self::fail('Expected the actor to be prevented from removing their own panel access.');
        } catch (ValidationException) {
            self::assertContains(Permission::ACCESS->value, $role->fresh()->permissions()->pluck('name')->all());
        }
    }

    private function administrator(): User
    {
        foreach ([Permission::ACCESS, Permission::ROLES_MANAGE] as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
        }

        $actor = User::factory()->create();
        $role = Role::create([
            'name' => 'role-safety-admin-' . $actor->id,
            'display_name' => 'Role safety administrator',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions([Permission::ACCESS->value, Permission::ROLES_MANAGE->value]);
        $actor->assignRole($role);

        return $actor;
    }
}
