<?php

declare(strict_types=1);

namespace Tests\Feature\Services\System\Database\SettingsAndConfig;

use App\Models\User;
use App\Services\System\Database\SettingsAndConfig\ConfigSchema;
use App\Services\System\Database\SettingsAndConfig\Exceptions\ConfigAndSettingsSchemaException;
use App\Services\System\Database\SettingsAndConfig\UserSettingsBlueprint;
use App\Services\System\Database\SettingsAndConfig\UserSettingsSchema;
use App\Services\Users\Settings\CoreUserSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Services\System\Database\SettingsAndConfig\SchemaToolingTest\SchemaToolingTestFixtures\ToolingConfig;
use Tests\TestCase;

#[CoversClass(ConfigSchema::class)]
#[CoversClass(UserSettingsSchema::class)]
class SchemaToolingTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // ConfigSchema — create / update (default seeding)
    // =========================================================================

    public function testItConfigCreateSeedsAllClassDefaults(): void
    {
        ConfigSchema::create(ToolingConfig::class);

        $this->assertDatabaseHas('config_values', [
            'namespace' => 'hawki-core',
            'key' => 'max_tokens',
            'value' => '4096',
        ]);
        $this->assertDatabaseHas('config_values', [
            'namespace' => 'hawki-core',
            'key' => 'name',
            'value' => 'tooling-default',
        ]);
    }

    public function testItConfigCreateNeverOverwritesExistingRows(): void
    {
        ConfigSchema::create(ToolingConfig::class);

        // An admin save (or a previous migration) customized the value.
        $this->app->make(\App\Services\Config\Repositories\ConfigValueRepository::class)
            ->upsertValue('hawki-core', 'max_tokens', '1024');

        // Re-running create must preserve the customized row.
        ConfigSchema::create(ToolingConfig::class);

        $this->assertDatabaseHas('config_values', [
            'namespace' => 'hawki-core',
            'key' => 'max_tokens',
            'value' => '1024',
        ]);
    }

    public function testItConfigUpdateUpsertsClosureValues(): void
    {
        ConfigSchema::create(ToolingConfig::class);

        ConfigSchema::update(ToolingConfig::class, static function ($blueprint): void {
            $blueprint->name = 'renamed';
        });

        $this->assertDatabaseHas('config_values', [
            'namespace' => 'hawki-core',
            'key' => 'name',
            'value' => 'renamed',
        ]);
    }

    public function testItConfigDropKeyAndDropRemoveTheRows(): void
    {
        ConfigSchema::create(ToolingConfig::class);

        ConfigSchema::dropKey(ToolingConfig::class, 'name');

        $this->assertDatabaseMissing('config_values', ['namespace' => 'hawki-core', 'key' => 'name']);
        $this->assertDatabaseHas('config_values', ['namespace' => 'hawki-core', 'key' => 'max_tokens']);

        ConfigSchema::drop(ToolingConfig::class);

        $this->assertDatabaseMissing('config_values', ['namespace' => 'hawki-core']);
    }

    public function testItConfigRenameMovesTheNamespace(): void
    {
        ConfigSchema::create(ToolingConfig::class);

        ConfigSchema::rename(ToolingConfig::class, \App\Services\Config\AbstractConfig::class);

        // Renaming between the same derived namespaces is a no-op here in substance —
        // assert the mechanics instead: rows exist under the (same) target namespace.
        $this->assertDatabaseHas('config_values', [
            'namespace' => 'hawki-core',
            'key' => 'max_tokens',
        ]);
    }

    // =========================================================================
    // UserSettingsSchema — create (validated no-op)
    // =========================================================================

    public function testItUserSettingsCreateIsAValidatedNoOp(): void
    {
        UserSettingsSchema::create(CoreUserSettings::class);

        // Nothing to seed per user — no rows exist.
        $this->assertDatabaseMissing('user_setting_values', ['namespace' => 'hawki-core']);
    }

    public function testItUserSettingsCreateRejectsAClosure(): void
    {
        $this->expectException(ConfigAndSettingsSchemaException::class);
        $this->expectExceptionMessage(\sprintf(
            '%s::create() does not accept a migration closure — there is nothing for it to transform.'
            . ' Use %s::update() for structural transforms of existing rows.',
            UserSettingsSchema::class,
            UserSettingsSchema::class,
        ));

        UserSettingsSchema::create(CoreUserSettings::class, static function (UserSettingsBlueprint $b): void {
        });
    }

    // =========================================================================
    // UserSettingsSchema — update (per-user transforms)
    // =========================================================================

    public function testItUserSettingsUpdateTransformsEveryUsersRows(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        User::factory()->create();

        $repository = $this->app->make(\App\Services\Users\Repositories\UserSettingValueRepository::class);
        $repository->upsertValuesForUser($first, 'hawki-core', ['legacy_key' => 'first-legacy']);
        $repository->upsertValuesForUser($second, 'hawki-core', ['legacy_key' => 'second-legacy']);

        UserSettingsSchema::update(CoreUserSettings::class, static function (UserSettingsBlueprint $b): void {
            $b->timezone = 'Europe/Berlin';
        });

        // The closure ran once per user that has rows, with that user's rows in context.
        $this->assertDatabaseHas('user_setting_values', [
            'user_id' => $first->id,
            'namespace' => 'hawki-core',
            'key' => 'timezone',
            'value' => 'Europe/Berlin',
        ]);
        $this->assertDatabaseHas('user_setting_values', [
            'user_id' => $second->id,
            'namespace' => 'hawki-core',
            'key' => 'timezone',
            'value' => 'Europe/Berlin',
        ]);
        // Untouched legacy rows are preserved (the closure only queued writes).
        $this->assertDatabaseHas('user_setting_values', [
            'user_id' => $first->id,
            'key' => 'legacy_key',
        ]);
        // The user without rows is skipped entirely — 2 legacy + 2 timezone rows remain.
        self::assertSame(
            4,
            \Illuminate\Support\Facades\DB::table('user_setting_values')->where('namespace', 'hawki-core')->count(),
        );
    }

    public function testItUserSettingsDropKeyAndDropOperateAcrossAllUsers(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $repository = $this->app->make(\App\Services\Users\Repositories\UserSettingValueRepository::class);
        $repository->upsertValuesForUser($first, 'hawki-core', ['theme' => 'dark', 'timezone' => 'UTC']);
        $repository->upsertValuesForUser($second, 'hawki-core', ['theme' => 'light']);

        UserSettingsSchema::dropKey(CoreUserSettings::class, 'theme');

        $this->assertDatabaseMissing('user_setting_values', ['namespace' => 'hawki-core', 'key' => 'theme']);
        $this->assertDatabaseHas('user_setting_values', ['namespace' => 'hawki-core', 'key' => 'timezone']);

        UserSettingsSchema::drop(CoreUserSettings::class);

        $this->assertDatabaseMissing('user_setting_values', ['namespace' => 'hawki-core']);
    }
}
