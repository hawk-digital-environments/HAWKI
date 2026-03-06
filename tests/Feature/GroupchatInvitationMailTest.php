<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Room;
use App\Models\Member;
use App\Models\Invitation;
use App\Jobs\SendEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GroupchatInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $invitedUser;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user who will send invitations
        $this->adminUser = User::factory()->create([
            'username' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Create user to be invited
        $this->invitedUser = User::factory()->create([
            'username' => 'inviteduser',
            'name' => 'Invited User',
            'email' => 'invited@example.com',
        ]);

        // Create a test room
        $this->room = Room::create([
            'slug' => 'test-room-' . uniqid(),
            'room_name' => 'Test Room',
            'room_description' => 'A test room for invitation emails',
            'creator_id' => $this->adminUser->id,
        ]);

        // Make admin user a member of the room
        Member::create([
            'room_id' => $this->room->id,
            'user_id' => $this->adminUser->id,
            'role' => 'admin',
        ]);
    }

    /**
     * Test that invitation email is queued when feature is enabled
     */
    public function test_invitation_email_is_queued_when_enabled(): void
    {
        // Enable the feature
        Config::set('hawki.send_groupchat_invitation_mails', true);
        
        // Fake the queue
        Queue::fake();

        // Act as admin user
        $this->actingAs($this->adminUser);

        // Send invitation
        $response = $this->postJson("/req/room/storeInvitations/{$this->room->slug}", [
            'invitations' => [
                [
                    'username' => $this->invitedUser->username,
                    'role' => 'viewer',
                    'iv' => 'test-iv',
                    'tag' => 'test-tag',
                    'encryptedRoomKey' => 'test-encrypted-key',
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Assert the email job was dispatched
        Queue::assertPushed(SendEmailJob::class, function ($job) {
            return $job->recipient === $this->invitedUser->email
                && str_contains($job->subjectLine, $this->room->room_name)
                && $job->viewTemplate === 'emails.invitation';
        });
    }

    /**
     * Test that invitation email is NOT queued when feature is disabled
     */
    public function test_invitation_email_not_queued_when_disabled(): void
    {
        // Disable the feature
        Config::set('hawki.send_groupchat_invitation_mails', false);
        
        // Fake the queue
        Queue::fake();

        // Act as admin user
        $this->actingAs($this->adminUser);

        // Send invitation
        $response = $this->postJson("/req/room/storeInvitations/{$this->room->slug}", [
            'invitations' => [
                [
                    'username' => $this->invitedUser->username,
                    'role' => 'viewer',
                    'iv' => 'test-iv',
                    'tag' => 'test-tag',
                    'encryptedRoomKey' => 'test-encrypted-key',
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Assert the email job was NOT dispatched
        Queue::assertNotPushed(SendEmailJob::class);
    }

    /**
     * Test that invitation email is NOT queued for existing invitations (updates)
     */
    public function test_invitation_email_not_sent_for_existing_invitation(): void
    {
        // Enable the feature
        Config::set('hawki.send_groupchat_invitation_mails', true);
        
        // Create an existing invitation
        Invitation::create([
            'room_id' => $this->room->id,
            'username' => $this->invitedUser->username,
            'role' => 'viewer',
            'iv' => 'old-iv',
            'tag' => 'old-tag',
            'invitation' => 'old-encrypted-key',
        ]);

        // Fake the queue
        Queue::fake();

        // Act as admin user
        $this->actingAs($this->adminUser);

        // Update invitation
        $response = $this->postJson("/req/room/storeInvitations/{$this->room->slug}", [
            'invitations' => [
                [
                    'username' => $this->invitedUser->username,
                    'role' => 'editor', // Changed role
                    'iv' => 'new-iv',
                    'tag' => 'new-tag',
                    'encryptedRoomKey' => 'new-encrypted-key',
                ]
            ]
        ]);

        $response->assertStatus(200);

        // Assert the email job was NOT dispatched (it's an update, not a new invitation)
        Queue::assertNotPushed(SendEmailJob::class);
    }

    /**
     * Test that invitation email contains correct data
     */
    public function test_invitation_email_contains_correct_data(): void
    {
        // Enable the feature
        Config::set('hawki.send_groupchat_invitation_mails', true);
        
        // Fake the queue
        Queue::fake();

        // Act as admin user
        $this->actingAs($this->adminUser);

        // Send invitation
        $this->postJson("/req/room/storeInvitations/{$this->room->slug}", [
            'invitations' => [
                [
                    'username' => $this->invitedUser->username,
                    'role' => 'viewer',
                    'iv' => 'test-iv',
                    'tag' => 'test-tag',
                    'encryptedRoomKey' => 'test-encrypted-key',
                ]
            ]
        ]);

        // Assert the email job contains expected data
        Queue::assertPushed(SendEmailJob::class, function ($job) {
            $emailData = $job->emailData;
            
            return $emailData['inviter_name'] === $this->adminUser->name
                && $emailData['room_name'] === $this->room->room_name
                && isset($emailData['url'])
                && str_contains($emailData['url'], $this->room->slug);
        });
    }

    /**
     * Test that multiple invitations trigger multiple emails
     */
    public function test_multiple_invitations_trigger_multiple_emails(): void
    {
        // Enable the feature
        Config::set('hawki.send_groupchat_invitation_mails', true);
        
        // Create another user
        $secondInvitedUser = User::factory()->create([
            'username' => 'inviteduser2',
            'email' => 'invited2@example.com',
        ]);

        // Fake the queue
        Queue::fake();

        // Act as admin user
        $this->actingAs($this->adminUser);

        // Send multiple invitations
        $this->postJson("/req/room/storeInvitations/{$this->room->slug}", [
            'invitations' => [
                [
                    'username' => $this->invitedUser->username,
                    'role' => 'viewer',
                    'iv' => 'test-iv-1',
                    'tag' => 'test-tag-1',
                    'encryptedRoomKey' => 'test-encrypted-key-1',
                ],
                [
                    'username' => $secondInvitedUser->username,
                    'role' => 'editor',
                    'iv' => 'test-iv-2',
                    'tag' => 'test-tag-2',
                    'encryptedRoomKey' => 'test-encrypted-key-2',
                ]
            ]
        ]);

        // Assert two email jobs were dispatched
        Queue::assertPushed(SendEmailJob::class, 2);
    }
}
