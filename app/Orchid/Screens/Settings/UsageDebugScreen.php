<?php

namespace App\Orchid\Screens\Settings;

use App\Models\Records\UsageRecord;
use App\Orchid\Layouts\Settings\UsageDebugLayout;
use App\Orchid\Layouts\Settings\UsageRecordFiltersLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class UsageDebugScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(?Request $request = null): iterable
    {
        return [
            'usage_records' => UsageRecord::with(['user', 'room'])
                ->filters(UsageRecordFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->limit(100)
                ->get(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Usage Debug';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'View and analyze AI model usage records for debugging purposes.';
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.usage.debug',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Refresh')
                ->icon('arrow-clockwise')
                ->method('refresh'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            UsageRecordFiltersLayout::class,
            UsageDebugLayout::class,
        ];
    }

    /**
     * Refresh the usage records data
     */
    public function refresh()
    {
        Toast::info('Usage records refreshed');
    }
}
