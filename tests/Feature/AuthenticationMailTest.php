<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

class AuthenticationMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that welcome email is sent when user is approved during registration
     */
    public function test_welcome_email_sent_for_approved_user(): void
    {
        // Enable the feature
        Config::set('hawki.send_registration_mails', true);
        Config::set('auth.local_needapproval', false); // Auto-approve

        // Mock the EmailService
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->andReturn(true);

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Setup session data for a new local user
        Session::put('authenticatedUserInfo', json_encode([
            'username' => 'approveduser',
            'name' => 'Approved User',
            'email' => 'approved@example.com',
            'employeetype' => 'student',
        ]));
        Session::put('first_login_local_user', true);

        // Make the complete registration request
        $response = $this->postJson('/completeregistration', [
            'publicKey' => 'test-public-key',
            'keychain' => 'test-keychain',
            'KCIV' => 'test-iv',
            'KCTAG' => 'test-tag',
            'newPassword' => 'newPassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test that approval pending email is sent when user needs approval during registration
     */
    public function test_approval_pending_email_sent_for_unapproved_user(): void
    {
        // Enable the feature
        Config::set('hawki.send_registration_mails', true);
        Config::set('auth.local_needapproval', true); // Require approval

        // Mock the EmailService
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('sendApprovalPendingEmail')
            ->once()
            ->andReturn(true);

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Setup session data for a new local user
        Session::put('authenticatedUserInfo', json_encode([
            'username' => 'pendinguser',
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'employeetype' => 'student',
        ]));
        Session::put('first_login_local_user', true);

        // Make the complete registration request
        $response = $this->postJson('/completeregistration', [
            'publicKey' => 'test-public-key-2',
            'keychain' => 'test-keychain-2',
            'KCIV' => 'test-iv-2',
            'KCTAG' => 'test-tag-2',
            'newPassword' => 'newPassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test that no emails are sent when feature is disabled
     */
    public function test_no_emails_sent_when_feature_disabled(): void
    {
        // Disable the feature
        Config::set('hawki.send_registration_mails', false);

        // Mock the EmailService - should NOT be called
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldNotReceive('sendWelcomeEmail');
        $emailServiceMock->shouldNotReceive('sendApprovalPendingEmail');

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Setup session data for a new local user
        Session::put('authenticatedUserInfo', json_encode([
            'username' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'employeetype' => 'student',
        ]));
        Session::put('first_login_local_user', true);

        // Make the complete registration request
        $response = $this->postJson('/completeregistration', [
            'publicKey' => 'test-public-key-3',
            'keychain' => 'test-keychain-3',
            'KCIV' => 'test-iv-3',
            'KCTAG' => 'test-tag-3',
            'newPassword' => 'newPassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test that approval email is sent when admin changes approval from false to true
     */
    public function test_approval_email_sent_when_admin_approves_user(): void
    {
        // Enable the feature
        Config::set('hawki.send_registration_mails', true);

        // Create a user with approval = false
        $user = User::factory()->create([
            'username' => 'unapproveduser',
            'email' => 'unapproved@example.com',
            'approval' => false,
            'auth_type' => 'local',
        ]);

        // Mock the EmailService
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('sendApprovalEmail')
            ->once()
            ->with(Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            }))
            ->andReturn(true);

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Admin changes approval to true
        $user->approval = true;
        $user->save();

        // Verify the user is now approved
        $this->assertTrue($user->fresh()->approval);
    }

    /**
     * Test that approval revoked email is sent when admin changes approval from true to false
     */
    public function test_approval_revoked_email_sent_when_admin_revokes_access(): void
    {
        // Enable the feature
        Config::set('hawki.send_registration_mails', true);

        // Create a user with approval = true
        $user = User::factory()->create([
            'username' => 'approveduser2',
            'email' => 'approved2@example.com',
            'approval' => true,
            'auth_type' => 'local',
        ]);

        // Mock the EmailService
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('sendApprovalRevokedEmail')
            ->once()
            ->with(Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            }))
            ->andReturn(true);

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Admin changes approval to false
        $user->approval = false;
        $user->save();

        // Verify the user is now unapproved
        $this->assertFalse($user->fresh()->approval);
    }

    /**
     * Test that registration completes successfully even if email fails
     */
    public function test_registration_completes_even_if_email_fails(): void
    {
        // Enable the feature
        Config::set('hawki.send_registration_mails', true);
        Config::set('auth.local_needapproval', false);

        // Mock the EmailService to throw an exception
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->andThrow(new \Exception('Email service unavailable'));

        $this->app->instance(EmailService::class, $emailServiceMock);

        // Setup session data for a new local user
        Session::put('authenticatedUserInfo', json_encode([
            'username' => 'testuser4',
            'name' => 'Test User 4',
            'email' => 'test4@example.com',
            'employeetype' => 'student',
        ]));
        Session::put('first_login_local_user', true);

        // Make the complete registration request
        $response = $this->postJson('/completeregistration', [
            'publicKey' => 'test-public-key-4',
            'keychain' => 'test-keychain-4',
            'KCIV' => 'test-iv-4',
            'KCTAG' => 'test-tag-4',
            'newPassword' => 'newPassword123',
        ]);

        // Registration should still succeed
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
