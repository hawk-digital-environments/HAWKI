<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Admin\BrandFonts;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the interface font an administrator uploaded. Public, because the sign-in page uses it too.
 */
class BrandFontController extends Controller
{
    public function __invoke(string $file, BrandFonts $fonts): Response
    {
        return $fonts->response($file, BrandFonts::isFile($file) ? BrandFonts::mediaType($file) : '') ?? abort(404);
    }
}
