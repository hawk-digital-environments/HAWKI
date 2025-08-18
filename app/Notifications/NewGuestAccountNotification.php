<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Orchid\Platform\Notifications\DashboardChannel;
use Orchid\Platform\Notifications\DashboardMessage;
use Orchid\Support\Color;

class NewGuestAccountNotification extends Notification
{
    use Queueable;

    private User $newUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $newUser)
    {
        $this->newUser = $newUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [DashboardChannel::class];
    }

    /**
     * Get the dashboard representation of the notification.
     */
    public function toDashboard(object $notifiable): DashboardMessage
    {
        $userRole = $this->newUser->employeetype ?? 'Unknown';
        
        return DashboardMessage::make()
            ->title('Neuer Guest-Account erstellt')
            ->message("Benutzer {$this->newUser->name} ({$this->newUser->email}) hat sich als {$userRole} registriert.")
            ->action(route('platform.systems.users.edit', $this->newUser))
            ->type(Color::INFO);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->newUser->id,
            'username' => $this->newUser->username,
            'email' => $this->newUser->email,
            'employeetype' => $this->newUser->employeetype,
        ];
    }
}
