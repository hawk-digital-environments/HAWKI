<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class ProviderIconsTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;
    private const BASE = '/api/hawki/v1/admin-providers';

    public function testIconEndpointsRequireAnAdministrator(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'staff']));
        $this->getJson(self::BASE . '/actions/icons')->assertForbidden();
        $this->postJson(self::BASE . '/actions/icon-upload')->assertForbidden();
        Http::assertNothingSent();
    }

    public function testUploadSaveReadReplaceAndRemove(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        Http::preventStrayRequests();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" style="fill:none;stroke:#000000;stroke-width:1.34899998;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:10;stroke-dasharray:none;stroke-opacity:1"/></svg>';
        $icon = $this->post(self::BASE . '/actions/icon-upload', [
            'image' => UploadedFile::fake()->createWithContent('custom.svg', $svg),
        ], ['Accept' => 'application/json'])->assertOk()->json('icon');
        self::assertSame($svg, $icon['svg']);
        $values = ['name' => 'Icon test', 'provider_id' => 'icon-test', 'adapter_key' => 'openai', 'active' => true, 'icon' => $icon];
        $id = $this->postAdminResource(self::BASE, ['values' => $values])->assertSuccessful()->json('data.id');
        $provider = AiProvider::withoutGlobalScopes()->findOrFail($id);
        self::assertSame($svg, $provider->icon['svg']);
        self::assertSame('data:image/svg+xml;base64,' . base64_encode($svg), $provider->icon_url);
        $row = collect($this->getJson(self::BASE . '?filter[search]=icon-test')->assertOk()->json('data'))->firstWhere('id', $id);
        self::assertEquals($icon, $row['attributes']['icon']);
        self::assertSame($provider->icon_url, $row['attributes']['icon_url']);
        $values['icon']['svg'] = str_replace('h24', 'h12', $svg);
        $this->patchAdminResource(self::BASE . '/' . $id, ['values' => $values, 'version' => $row['meta']['version']])->assertSuccessful();
        self::assertSame($values['icon']['svg'], $provider->fresh()->icon['svg']);
        $row = collect($this->getJson(self::BASE . '?filter[search]=icon-test')->assertOk()->json('data'))->firstWhere('id', $id);
        $values['icon'] = null;
        $this->patchAdminResource(self::BASE . '/' . $id, ['values' => $values, 'version' => $row['meta']['version']])->assertSuccessful();
        self::assertNull($provider->fresh()->icon);
        self::assertNull($provider->fresh()->icon_url);
        Http::assertNothingSent();
    }

    public function testUploadRejectsActiveSvgAndOversizeFiles(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));

        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            str_repeat('x', 262145),
        ] as $content) {
            $this->post(self::BASE . '/actions/icon-upload', ['image' => UploadedFile::fake()->createWithContent('bad.svg', $content)], ['Accept' => 'application/json'])->assertUnprocessable();
        }
    }

    public function testOpenAiLikeProviderCanBeCreatedWithCredentialsAndCatalogueIcon(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        config(['cache.default' => 'array']);
        Http::preventStrayRequests();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';
        Http::fake([
            'https://api.svgl.app' => Http::response([
                ['id' => 1, 'title' => 'Amazon Q', 'route' => [
                    'light' => 'https://svgl.app/library/test-light.svg',
                    'dark' => 'https://svgl.app/library/test-dark.svg',
                ]],
            ]),
            'https://svgl.app/library/test-*.svg' => Http::response($svg),
        ]);
        $values = [
            'name' => 'JLU', 'provider_id' => 'jlu-icon-test', 'adapter_key' => 'openai_like', 'active' => true,
            'api_url' => 'https://api.hrz.uni-giessen.de/v1',
            'model_status_url' => 'https://api.hrz.uni-giessen.de/health',
            'api_key' => 'test-only-key',
            'icon' => ['source' => 'svgl', 'svgl_id' => 1, 'title' => 'Amazon Q'],
        ];
        $response = $this->postAdminResource(self::BASE, ['values' => $values]);
        self::assertSame(201, $response->status(), json_encode($response->json('errors')));
        $id = $response->json('data.id');
        $provider = AiProvider::withoutGlobalScopes()->findOrFail($id);
        self::assertSame('openai_like', $provider->adapter_key);
        self::assertSame($values['api_url'], $provider->api_url);
        self::assertSame($values['model_status_url'], $provider->model_status_url);
        self::assertSame($values['api_key'], $provider->api_key);
        self::assertSame($svg, $provider->icon['svg']);
        self::assertSame($svg, $provider->icon['svg_dark']);
        Http::assertSentCount(3);
    }

    public function testItResolvesRemoteIconsBeforeOpeningTheMutationTransaction(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        config(['cache.default' => 'array']);
        $transactionLevel = DB::transactionLevel();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';
        Http::fake(static function ($request) use ($transactionLevel, $svg) {
            static::assertSame($transactionLevel, DB::transactionLevel(), 'Remote icon reads must not hold admin mutation locks.');

            return Http::response(str_starts_with($request->url(), 'https://api.svgl.app')
                ? [['id' => 987, 'title' => 'Lock test', 'route' => 'https://svgl.app/library/lock-test.svg']]
                : $svg);
        });
        $values = ['name' => 'Lock test', 'provider_id' => 'icon-lock-test', 'adapter_key' => 'openai', 'active' => true,
            'icon' => ['source' => 'svgl', 'svgl_id' => 987, 'title' => 'Lock test']];

        $created = $this->postAdminResource(self::BASE, ['values' => $values])->assertCreated();
        Http::assertSentCount(2);

        $id = $created->json('data.id');
        $version = $created->json('data.meta.version');
        // Reusing the catalogue selection uses the cached SVG and catalogue.
        $updated = $this->patchAdminResource(self::BASE . '/' . $id, ['values' => ['name' => 'Updated'] + $values, 'version' => $version])->assertOk();
        Http::assertSentCount(2);

        $this->patchAdminResource(self::BASE . '/' . $id, ['values' => ['name' => 'Stale'] + $values, 'version' => $version])->assertStatus(412);
        self::assertSame('Updated', AiProvider::withoutGlobalScopes()->findOrFail($id)->name);
        self::assertNotSame($version, $updated->json('data.meta.version'));
    }

}
