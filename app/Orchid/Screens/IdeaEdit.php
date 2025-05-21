<?php

namespace App\Orchid\Screens;

use Orchid\Screen\Screen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdeaEdit extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $totalUsers = DB::table('users')->count();
        Log::info('Total users: ' . $totalUsers);
        return [
            'totalUsers' => $totalUsers,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'IdeaEdit';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [];
    }
}
