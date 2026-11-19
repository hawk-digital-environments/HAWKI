<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * The creator of a visible assistant must be visible with it, even to users
 * who share no group chat with them (the User `access` scope would hide them).
 */
#[CoversNothing()]
class AssistantCreatorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function testStrangerSeesCreatorOfReleasedAssistantInList(): void
    {
        $creator = User::factory()->create(['name' => 'Creator One']);
        $remixSource = User::factory()->create(['name' => 'Remix Source']);
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'remixed_creator_id' => $remixSource->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);

        $this->actingAsUser(User::factory()->create());

        $response = $this->jsonApiRaw('get', '/api/hawki/v1/assistants?include=creator,remix_creator')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $assistant->id)
            ->assertJsonPath('data.0.relationships.creator.data.id', (string) $creator->id)
            ->assertJsonPath('data.0.relationships.remix_creator.data.id', (string) $remixSource->id);

        $included = collect($response->json('included'))->keyBy('id');
        self::assertSame('Creator One', $included[(string) $creator->id]['attributes']['display_name'] ?? null);
        self::assertSame('Remix Source', $included[(string) $remixSource->id]['attributes']['display_name'] ?? null);
    }

    public function testStrangerSeesCreatorOnShowAndRelatedEndpoint(): void
    {
        $creator = User::factory()->create(['name' => 'Creator One']);
        $assistant = Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => AssistantReleaseStage::FEDERATED->value,
        ]);

        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}?include=creator")
            ->assertOk()
            ->assertJsonPath('data.relationships.creator.data.id', (string) $creator->id);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}/creator")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $creator->id);
    }
}
