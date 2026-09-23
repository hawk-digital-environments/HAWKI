<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\SystemSettings;
use App\Services\Config\EnvironmentConfigProxy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AdminSettingsApiTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;
    private const BASE = '/api/hawki/v1/admin-settings';

    public function testSettingsExposeVersionsAndRequireCurrentVersionsForSaveAndReset(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $row = $this->setting('APP_NAME');

        self::assertSame('environment', $row['attributes']['source']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['meta']['version']);
        $this->patchAdminResource(self::BASE . '/APP_NAME', ['values' => ['value' => 'Ignored']])->assertStatus(412);

        $saved = $this->patchAdminResource(self::BASE . '/APP_NAME', [
            'version' => $row['meta']['version'],
            'values' => ['value' => 'Admin name'],
        ])->assertOk();
        self::assertSame('database', $saved->json('data.attributes.source'));
        self::assertNotSame($row['meta']['version'], $saved->json('data.meta.version'));
        self::assertSame('Admin name', json_decode(DB::table('admin_settings')->where('key', 'APP_NAME')->value('value'), true));

        $this->patchAdminResource(self::BASE . '/APP_NAME', [
            'version' => $row['meta']['version'],
            'values' => ['value' => 'Stale write'],
        ])->assertStatus(412);
        $this->deleteAdminResource(self::BASE . '/APP_NAME')->assertStatus(412);
        $this->deleteAdminResource(self::BASE . '/APP_NAME', [
            'version' => $row['meta']['version'],
        ])->assertStatus(412);
        self::assertSame('Admin name', json_decode(DB::table('admin_settings')->where('key', 'APP_NAME')->value('value'), true));

        $this->deleteAdminResource(self::BASE . '/APP_NAME', [
            'version' => $saved->json('data.meta.version'),
        ])->assertNoContent();
        $this->assertDatabaseMissing('admin_settings', ['key' => 'APP_NAME']);
    }

    public function testSettingVersionsReflectDeploymentDefaultsAndTheCollectionSupportsIdFilters(): void
    {
        $admin = User::factory()->create(['employeetype' => 'admin']);
        $this->actingAs($admin);
        $before = $this->setting('SESSION_LIFETIME');
        config(['session.lifetime' => ((int) $before['attributes']['default']) + 1]);
        app()->instance(SystemSettings::class, new SystemSettings(new EnvironmentConfigProxy(config())));
        $after = $this->setting('SESSION_LIFETIME');

        self::assertNotSame($before['meta']['version'], $after['meta']['version']);
        self::assertSame($after['attributes']['default'], $after['attributes']['value']);

        $other = User::factory()->create(['employeetype' => 'staff']);
        $users = $this->get('/api/hawki/v1/admin-users?filter[where][id]=' . $other->id)
            ->assertOk()
            ->json('data');
        self::assertCount(1, $users);
        self::assertSame((string) $other->id, $users[0]['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function setting(string $key): array
    {
        return collect($this->get(self::BASE)->assertOk()->json('data'))->firstWhere('id', $key);
    }
}
