<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;

class ScheduleListLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'schedules';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('indicator', '')
                ->width('20px')
                ->render(function ($schedule) {
                    $isEnabled = $schedule['is_enabled'] ?? true;
                    
                    if ($isEnabled) {
                        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="text-success" viewBox="0 0 16 16">
                            <circle cx="8" cy="8" r="8"/>
                        </svg>';
                    } else {
                        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="text-secondary" viewBox="0 0 16 16">
                            <circle cx="8" cy="8" r="8"/>
                        </svg>';
                    }
                }),

            TD::make('command', __('Command'))
                ->render(function ($schedule) {
                    return '<code>' . e($schedule['command']) . '</code>';
                }),

            TD::make('description', __('Description'))
                ->render(function ($schedule) {
                    return $schedule['description'] ?? '-';
                }),

            TD::make('expression_human', __('Schedule'))
                ->render(function ($schedule) {
                    return e($schedule['expression_human']);
                }),

            TD::make('next_run_timestamp', __('Next Due'))
                ->render(function ($schedule) {
                    $isEnabled = $schedule['is_enabled'] ?? true;
                    
                    if (!$isEnabled) {
                        return '<span class="text-muted">-</span>';
                    }
                    
                    return $schedule['next_due'];
                }),

            TD::make('actions', __('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function ($schedule) {
                    $isEnabled = $schedule['is_enabled'] ?? true;
                    $taskKey = $schedule['task_key'] ?? null;
                    
                    if (!$taskKey) {
                        return '-';
                    }
                    
                    $actions = [];
                    
                    if ($isEnabled) {
                        $actions[] = Button::make(__('Deactivate'))
                            ->icon('bs.pause-circle')
                            ->method('toggleSchedule', [
                                'task' => $taskKey,
                                'action' => 'disable'
                            ])
                            ->confirm(__('Deactivate this scheduled task?'))
                            ->canSee(\Auth::user()->hasAccess('platform.systems.settings'));
                    } else {
                        $actions[] = Button::make(__('Activate'))
                            ->icon('bs.play-circle')
                            ->method('toggleSchedule', [
                                'task' => $taskKey,
                                'action' => 'enable'
                            ])
                            ->confirm(__('Activate this scheduled task?'))
                            ->canSee(\Auth::user()->hasAccess('platform.systems.settings'));
                    }
                    
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list($actions);
                }),
        ];
    }
}
