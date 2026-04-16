<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Orchid\Platform\Notifications\DashboardChannel;
use Orchid\Support\Color;

class BackupOperationNotification extends Notification
{
    use Queueable;

    private string $message;
    private string $type;
    private string $title;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $message, string $type = 'info')
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
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
        // Map type to Orchid Color
        $color = match($this->type) {
            'success' => Color::SUCCESS,
            'error' => Color::DANGER,
            'warning' => Color::WARNING,
            default => Color::INFO,
        };

        return [
            'title' => $this->title,
            'message' => $this->message,
            'action' => url('/admin/settings/backup'),
            'type' => $color->name(),
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
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }
}
