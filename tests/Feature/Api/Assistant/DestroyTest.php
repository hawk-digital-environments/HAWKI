<?php

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_assistant(): void
    {
        $user = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/assistants/{$assistant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('assistants', ['id' => $assistant->id]);
    }

    public function test_cannot_delete_other_user_assistant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->deleteJson("/api/assistants/{$assistant->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('assistants', ['id' => $assistant->id]);
    }
}
