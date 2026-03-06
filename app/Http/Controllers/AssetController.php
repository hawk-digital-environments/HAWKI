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
        // Only custom-styles is managed in the database
        if ($name === 'custom-styles') {
            $css = $cache->rememberForeverIfNotNull(
                $name,
                static fn() => AppCss::getByName($name),
                '/* CSS not found */'
            );
        } else {
            // All other CSS files are loaded directly from the filesystem
            $cssPath = public_path("css/{$name}.css");
            if (!file_exists($cssPath)) {
                return response('/* CSS file not found: ' . $name . ' */')
                    ->header('Content-Type', 'text/css')
                    ->setStatusCode(404);
            }
            $css = file_get_contents($cssPath);
        }

        return response($css)->header('Content-Type', 'text/css');
    }
}
