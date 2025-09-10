<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

trait UrlGenerator
{

    protected function generateUrl(string $path, string $uuid, string $category): string
    {
//        $driver = config("filesystems.disks.{$this->disk}.driver");
//        $visibility = config("filesystems.disks.{$this->disk}.visibility", 'private');
        $driver = $this->config['driver'];
        $visibility = $this->config['visibility'];

        switch ($driver) {
            case 's3':
            case 'webdav': // Nextcloud (if you extend it with temp URL support)
                // Prefer native temporaryUrl if supported
                if (method_exists($this->disk, 'temporaryUrl')) {
                    return $this->disk->temporaryUrl($path, now()->addHours(24));
                }
                break;

            case 'local':
                // Local "public" disk can return direct URLs
                if ($visibility === 'public' && $this->disk->url($path)) {
                    return $this->disk->url($path);
                }

                // Local private disk → fallback to signed route
                return URL::temporarySignedRoute(
                    "files.download.{$category}",
                    now()->addHours(24),
                    [
                        'uuid'     => $uuid,
                        'category' => $category,
                        'path'     => base64_encode($path),
                        'disk'     => $this->disk, // pass disk explicitly
                    ]
                );

            case 'sftp':
                // No direct URL, always proxy through Laravel
                return URL::temporarySignedRoute(
                    "files.download.{$category}",
                    now()->addHours(24),
                    [
                        'uuid'     => $uuid,
                        'category' => $category,
                        'path'     => base64_encode($path),
                        'disk'     => $this->disk,
                    ]
                );

            default:
                // Fallback: try native url() if available
                if (method_exists($this->disk, 'url')) {
                    return $this->disk->url($path);
                }

                // As a last resort → proxy route
                return URL::temporarySignedRoute(
                    "files.download.{$category}",
                    now()->addHours(24),
                    [
                        'uuid'     => $uuid,
                        'category' => $category,
                        'path'     => base64_encode($path),
                        'disk'     => $this->disk,
                    ]
                );
        }
    }
}
