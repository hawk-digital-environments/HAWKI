<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Admin\BrandMedia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the sign-in background an administrator uploaded. Public, because it is shown before sign-in.
 */
class BrandMediaController extends Controller
{
    public function __invoke(string $file, BrandMedia $media): Response
    {
        return $media->response($file, BrandMedia::isFile($file) ? BrandMedia::mediaType($file) : '') ?? abort(404);
    }
}
