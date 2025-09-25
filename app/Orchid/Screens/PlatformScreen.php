<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\AppSystemImage;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Get the logo_svg from the database with caching
        $logoSvg = Cache::remember('system_image_logo_svg', 3600, function () {
            $systemImage = AppSystemImage::where('name', 'logo_svg')->where('active', true)->first();

            return $systemImage ? $systemImage->file_path : null;
        });

        return [
            'logoSvg' => $logoSvg,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Get Started';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Welcome to your HAWKI admin panel.';
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
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('platform::partials.update-assets'),
            Layout::view('orchid.partials.welcome'), // Use our custom welcome view in orchid directory
        ];
    }
}
