<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Http\Controllers\Assistant\AssistantReviewController;
use App\Http\Requests\Assistant\AssistantReviewPermissionsRequest;
use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrgMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\TestCase;

/**
 * The `actions/permissions` probe behind the admin review menu's visibility.
 */
#[CoversClass(AssistantReviewPermissionsRequest::class)]
#[CoversClass(AssistantReviewController::class)]
#[CoversFunction(OrgMembership::class, 'isAdminAnywhere')]
class AssistantReviewPermissionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItAllowsAnOrgAdminToDecideReviews(): void
    {
        $this->actingAsUser($this->createAdmin());

        $this->getJson('/api/hawki/v1/assistant-reviews/actions/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['updateAny' => 'ALLOWED']]);
    }

    public function testItDeniesAPlainOrgMember(): void
    {
        $this->actingAsUser($this->createMember());

        $this->getJson('/api/hawki/v1/assistant-reviews/actions/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['updateAny' => 'DENIED']]);
    }

    public function testItDeniesACreatorWithoutAdminRole(): void
    {
        // Creators may view their own review rows but never decide them.
        $user = User::factory()->create();
        Assistant::factory()->create(['creator_id' => $user->id]);
        $this->actingAsUser($user);

        $this->getJson('/api/hawki/v1/assistant-reviews/actions/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['updateAny' => 'DENIED']]);
    }

    public function testItDeniesAUserWithoutAnyOrganization(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->getJson('/api/hawki/v1/assistant-reviews/actions/permissions')
            ->assertOk()
            ->assertExactJson(['data' => ['updateAny' => 'DENIED']]);
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $this->getJson('/api/hawki/v1/assistant-reviews/actions/permissions')
            ->assertForbidden();
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach(Organization::first(), ['role' => 'admin']);

        return $user;
    }

    private function createMember(): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach(Organization::first(), ['role' => 'member']);

        return $user;
    }
}
