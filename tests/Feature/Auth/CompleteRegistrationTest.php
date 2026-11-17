<?php
declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class CompleteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase disables this by default; this suite covers the assignment itself.
        config()->set('hawki.organization.default', 'HAWKI');
    }

    protected function completeRegistration(AuthenticatedUserInfo $userInfo): TestResponse
    {
        return $this->withSession([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode($userInfo),
        ])->post('/req/complete_registration');
    }

    public function testItCreatesTheUserAndAttachesThemToTheDefaultOrganization(): void
    {
        $organizationId = DB::table('organizations')->where('name', 'HAWKI')->value('id');
        static::assertNotNull($organizationId, 'The initial migration should have created the "HAWKI" organization.');

        $response = $this->completeRegistration(
            new AuthenticatedUserInfo('testuser', 'Test User', 'testuser@example.com', 'tester')
        );

        $response->assertOk()->assertJsonPath('success', true);

        $userId = DB::table('users')->where('username', 'testuser')->value('id');
        static::assertNotNull($userId);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => 'member',
        ]);
    }

    public function testItRegistersWithoutOrganizationWhenTheDefaultOrganizationIsMissing(): void
    {
        config()->set('hawki.organization.default', 'Nonexistent Organization');

        $response = $this->completeRegistration(
            new AuthenticatedUserInfo('testuser', 'Test User', 'testuser@example.com', 'tester')
        );

        // A misconfigured default organization must never break the registration itself.
        $response->assertOk()->assertJsonPath('success', true);

        $userId = DB::table('users')->where('username', 'testuser')->value('id');
        static::assertNotNull($userId);
        $this->assertDatabaseMissing('organization_user', ['user_id' => $userId]);
    }
}
