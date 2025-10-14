<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\URL;

class UrlGenerator
{
    private string $path;
    private string $uuid;
    private string $category;
    private string $visibility;


    public function __construct(
        protected array $config,
        protected Filesystem $disk
    )
    {
    }

    public function generate(string $path, string $uuid, string $category): string
    {
        $this->uuid = $uuid;
        $this->path = $path;
        $this->category = $category;
        $this->visibility = $this->config['visibility'];


        return match ($this->config['driver']) {
            's3', 'webdav' => $this->generateTemporaryUrl(),
            'local' => $this->generateLocalUrl(),
            'sftp' => $this->generateSftpUrl(),
            default => $this->generateDefaultUrl(),
        };
    }

    private function generateLocalUrl(): string{
        // Local "public" disk can return direct URLs
        if ($this->visibility === 'public' && $this->disk->url($this->path)) {
            return $this->disk->url($this->path);
        }

        // Local private disk → fallback to signed route
        return URL::temporarySignedRoute(
            "files.download.{$this->category}",
            now()->addHours(24),
            [
                'uuid'     => $this->uuid,
                'category' => $this->category,
                'path'     => base64_encode($this->path),
                'disk'     => $this->disk, // pass disk explicitly
            ]
        );
    }

    private function generateSftpUrl(): string{
        // No direct URL, always proxy through Laravel
        return URL::temporarySignedRoute(
            "files.download.{$this->category}",
            now()->addHours(24),
            [
                'uuid'     => $this->uuid,
                'category' => $this->category,
                'path'     => base64_encode($this->path),
                'disk'     => $this->disk,
            ]
        );
    }

    private function generateTemporaryUrl(): string{
        // Prefer native temporaryUrl if supported
        if (method_exists($this->disk, 'temporaryUrl')) {
            return $this->disk->temporaryUrl($this->path, now()->addHours(24));
        }
        return $this->generateDefaultUrl();
    }

    private function generateDefaultUrl(): string{
        // Fallback: try native url() if available
        if (method_exists($this->disk, 'url')) {
            return $this->disk->url($this->path);
        }

        // As a last resort → proxy route
        return URL::temporarySignedRoute(
            "files.download.{$this->category}",
            now()->addHours(24),
            [
                'uuid'     => $this->uuid,
                'category' => $this->category,
                'path'     => base64_encode($this->path),
                'disk'     => $this->disk,
            ]
        );
    }
}
