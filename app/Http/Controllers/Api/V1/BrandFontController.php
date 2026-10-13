<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Admin\BrandFonts;
use Illuminate\Http\Response;

/**
 * Serves the interface fonts an administrator uploaded. Public, because the sign-in page uses them too.
 */
class BrandFontController extends Controller
{
    public function __invoke(string $file, BrandFonts $fonts): Response
    {
        $bytes = $fonts->read($file);
        abort_if(null === $bytes, 404);

        // The name is the content hash, so the file behind a URL never changes.
        return response($bytes, 200, [
            'Content-Type' => BrandFonts::mediaType($file),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
