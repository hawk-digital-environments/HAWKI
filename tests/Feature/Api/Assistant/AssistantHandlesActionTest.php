<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Requests\Assistant\AssistantHandlesRequest;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\TestCase;

/**
 * The assistants `actions/handles` query: which of the comma-separated
 * handles exist — globally, regardless of visibility, disclosing the
 * handles only.
 */
#[CoversClass(AssistantHandlesRequest::class)]
#[CoversClass(AssistantController::class)]
#[CoversFunction(AssistantRepository::class, 'findExistingHandles')]
class AssistantHandlesActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItAnswersWithTheExistingHandlesInQueryOrder(): void
    {
        $this->actingAsUser(User::factory()->create());
        $other = User::factory()->create();
        // The first three exist (one of them invisible to the acting user);
        // the last two do not.
        $this->createAssistant($other, 'tutor');
        $this->createAssistant($other, 'tutor-2', 'federated');
        $this->createAssistant($other, 'writing-help');

        $response = $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=tutor,missing-one,tutor-2,writing-help,missing-two');

        $response->assertOk()->assertExactJson(['data' => ['tutor', 'tutor-2', 'writing-help']]);
    }

    public function testASingleHandleAnswersWithOneOrNoEntries(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->createAssistant(User::factory()->create(), 'tutor');

        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=tutor')
            ->assertOk()->assertExactJson(['data' => ['tutor']]);
        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=free-handle')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function testAnAbsentOrEmptyFilterAnswersWithAnEmptyList(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->createAssistant(User::factory()->create(), 'tutor');

        $this->getJson('/api/hawki/v1/assistants/actions/handles')
            ->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function testDuplicateHandlesAreIgnored(): void
    {
        $this->actingAsUser(User::factory()->create());
        $this->createAssistant(User::factory()->create(), 'tutor');

        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=tutor,tutor')
            ->assertOk()->assertExactJson(['data' => ['tutor']]);
    }

    public function testTheHandleCharsetIsEnforced(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=not a handle')
            ->assertUnprocessable();
        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=' . str_repeat('a', 256))
            ->assertUnprocessable();
    }

    public function testAuthenticationIsRequired(): void
    {
        $this->getJson('/api/hawki/v1/assistants/actions/handles?handles=tutor')->assertForbidden();
    }

    private function createAssistant(User $creator, string $handle, string $releaseStage = 'draft'): Assistant
    {
        return Assistant::factory()->create([
            'creator_id' => $creator->id,
            'release_stage' => $releaseStage,
            'handle' => $handle,
        ]);
    }
}
