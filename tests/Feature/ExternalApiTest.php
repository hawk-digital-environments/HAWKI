<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsTokenUser(): void
    {
        Sanctum::actingAs(User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'publicKey' => '',
            'employeetype' => 'staff',
        ]));
    }

    private function validPayload(): array
    {
        return [
            'payload' => [
                'model' => 'gpt-4.1-nano',
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hi']],
                ],
            ],
        ];
    }

    /** Payload without 'stream' must not 500 — see PR #220, ae539aa, ab5eb36. */
    public function test_external_api_does_not_crash_without_stream_field(): void
    {
        $this->actingAsTokenUser();

        $response = $this->postJson('/api/ai-req', $this->validPayload());

        $this->assertNotEquals(500, $response->status());
    }

    public function test_external_api_rejects_missing_model(): void
    {
        $this->actingAsTokenUser();

        $this->postJson('/api/ai-req', [
            'payload' => [
                'messages' => [['role' => 'user', 'content' => ['text' => 'Hi']]],
            ],
        ])->assertStatus(422);
    }

    public function test_external_api_rejects_unauthenticated(): void
    {
        $this->postJson('/api/ai-req', $this->validPayload())->assertStatus(401);
    }
}
