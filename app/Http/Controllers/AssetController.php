<?php
declare(strict_types=1);


namespace App\Http\Controllers;


use App\Models\AppCss;
use App\Models\AppSystemImage;
use App\Services\Frontend\CssCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssetController extends Controller
{
    public function getSystemImage(Request $request): RedirectResponse
    {
        $name = $request->route('name');

        $image = AppSystemImage::getByName($name);
        if ($image) {
            return redirect(asset($image->file_path));
        }

        // Fallback to static files
        $fallback = [
            'favicon' => 'favicon.ico',
            'logo_svg' => 'img/logo.svg'
        ];

        return redirect(asset($fallback[$name] ?? 'img/logo.svg'));
    }

    public function serveCss(
        string   $name,
        CssCache $cache
    ): Response
    {
        $css = $cache->rememberForeverIfNotNull(
            $name,
            static fn() => AppCss::getByName($name),
            '/* CSS not found */'
        );

        return response($css)->header('Content-Type', 'text/css');
    }
}
