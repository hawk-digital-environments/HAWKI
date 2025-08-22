<?php

namespace App\Listeners;

use App\Events\GuestAccountCreated;
use App\Models\User;
use App\Notifications\NewGuestAccountNotification;
use Illuminate\Support\Facades\Log;

class NotifyAdminsAboutGuestAccount
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(GuestAccountCreated $event): void
    {
        Log::info('Handling GuestAccountCreated event for user: ' . $event->user->username);

        // Find all admin users
        $adminUsers = $this->getAdminUsers();
        
        Log::info('Found ' . $adminUsers->count() . ' admin users for notification');

        // Send notification to each admin user synchronously
        foreach ($adminUsers as $admin) {
            try {
                $admin->notify(new NewGuestAccountNotification($event->user));
                Log::info('Notification sent to admin: ' . $admin->username . ' (' . $admin->email . ')');
            } catch (\Exception $e) {
                Log::error('Failed to send notification to admin ' . $admin->username . ': ' . $e->getMessage());
            }
        }

        Log::info('Completed sending notifications for guest account: ' . $event->user->username);
    }

    /**
     * Get all users with admin role
     */
    private function getAdminUsers()
    {
        return User::whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->get();
    }
}
