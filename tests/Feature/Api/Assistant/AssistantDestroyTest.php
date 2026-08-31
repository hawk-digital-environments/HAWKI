<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function testCanDeleteAssistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        $this->actingAsUser($user);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('assistants', ['id' => $assistant->id]);
    }

    public function testCannotDeleteOtherUserAssistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($other);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}")
            ->assertForbidden()
            ->assertJson(['errors' => [['detail' => 'This action is unauthorized.']]]);

        $this->assertDatabaseHas('assistants', ['id' => $assistant->id]);
    }
}
