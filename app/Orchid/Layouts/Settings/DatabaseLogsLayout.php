<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class DatabaseLogsLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'database_logs';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('channel', 'Channel')
                ->width('120px')
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->render(function ($log) {
                    $colors = [
                        'single' => 'badge bg-primary',
                        'daily' => 'badge bg-info',
                        'stack' => 'badge bg-secondary',
                        'database' => 'badge bg-success',
                        'slack' => 'badge bg-dark',
                        'stderr' => 'badge bg-warning',
                        'errorlog' => 'badge bg-danger',
                    ];
                    $class = $colors[$log->channel] ?? 'badge bg-light text-dark';
                    return "<span class='{$class}'>{$log->channel}</span>";
                }),

            TD::make('level', 'Level')
                ->width('80px')
                ->sort()
                ->filter(TD::FILTER_SELECT, [
                    'debug' => 'Debug',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error',
                    'critical' => 'Critical',
                ])
                ->render(function ($log) {
                    $colors = [
                        'debug' => 'text-muted',
                        'info' => 'text-info',
                        'warning' => 'text-warning', 
                        'error' => 'text-danger',
                        'critical' => 'text-danger bg-danger-subtle'
                    ];
                    $class = $colors[$log->level] ?? 'text-dark';
                    return "<span class='{$class}'>" . strtoupper($log->level) . "</span>";
                }),

            TD::make('message', 'Message')
                ->width('400px')
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->render(function ($log) {
                    $messagePreview = \Str::limit($log->message, 100);
                    
                    return ModalToggle::make($messagePreview)
                        ->modal('showContext')
                        ->modalTitle('Log Details')
                        ->method('showContext')
                        ->asyncParameters([
                            'log_id' => $log->id
                        ])
                        ->class('btn btn-link btn-sm p-0 text-start text-decoration-none')
                        ->style('white-space: normal; word-wrap: break-word;');
                }),

            TD::make('logged_at', 'Time')
                ->width('150px')
                ->sort()
                ->render(function ($log) {
                    return "<small>" . $log->logged_at->format('Y-m-d H:i:s') . "</small>";
                }),

            TD::make('user.name', 'User')
                ->width('120px')
                ->sort()
                ->filter(TD::FILTER_TEXT)
                ->render(function ($log) {
                    return $log->user ? "<small>{$log->user->name}</small>" : '<small class="text-muted">System</small>';
                }),
        ];
    }
}
