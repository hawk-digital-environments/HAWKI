<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Orchid\Platform\Notifications\DashboardChannel;
use Orchid\Support\Color;

class MaintenanceModeEnabled extends Notification
{
    use Queueable;

    /**
     * @var string
     */
    private $bypassUrl;

    /**
     * @var string
     */
    private $secret;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $bypassUrl, string $secret)
    {
        $this->bypassUrl = $bypassUrl;
        $this->secret = $secret;
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
    public function toDashboard(object $notifiable): array
    {
        return [
            'title' => '🔒 Maintenance Mode Active',
            'message' => 'The system is in maintenance mode.',
            'action' => url('/admin'),
            'bypass_url' => $this->bypassUrl,
            'type' => Color::WARNING->name(),
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'bypass_url' => $this->bypassUrl,
            'secret' => $this->secret,
        ];
    }
}
