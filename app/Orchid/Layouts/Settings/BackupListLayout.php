<?php

namespace App\Orchid\Layouts\Settings;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class BackupListLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'backups';

    /**
     * @var bool
     */
    protected $striped = true;

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('filename', __('Filename'))
                ->render(function ($backup) {
                    return '<code>' . e($backup['filename']) . '</code>';
                }),

            TD::make('size_human', __('Size'))
                ->render(function ($backup) {
                    return $backup['size_human'];
                }),

            TD::make('created_human', __('Created At'))
                ->render(function ($backup) {
                    return $backup['created_human'];
                }),

            TD::make('actions', __('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function ($backup) {
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Link::make(__('Download'))
                                ->icon('bs.download')
                                ->route('platform.systems.settings.backup.download', ['filename' => $backup['filename']])
                                ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
                            
                            ModalToggle::make(__('Restore'))
                                ->icon('bs.arrow-counterclockwise')
                                ->modal('restoreBackupModal')
                                ->method('restoreBackup')
                                ->asyncParameters([
                                    'filename' => $backup['filename'],
                                ])
                                ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
                            
                            Button::make(__('Delete'))
                                ->icon('bs.trash')
                                ->confirm(__('Are you sure you want to delete this backup? This action cannot be undone.'))
                                ->method('deleteBackup', [
                                    'filename' => $backup['filename'],
                                ])
                                ->canSee(\Auth::user()->hasAccess('platform.systems.settings')),
                        ]);
                }),
        ];
    }
}
