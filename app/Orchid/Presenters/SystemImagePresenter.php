<?php

declare(strict_types=1);

namespace App\Orchid\Presenters;

use Laravel\Scout\Builder;
use Orchid\Screen\Contracts\Personable;
use Orchid\Screen\Contracts\Searchable;
use Orchid\Support\Presenter;

class SystemImagePresenter extends Presenter implements Personable, Searchable
{
    /**
     * System images configuration
     */
    protected $systemImages = [
        'logo_svg' => [
            'title' => 'Logo (SVG)',
            'description' => 'Vector version of the application logo (SVG format)',
            'format' => 'SVG',
            'path' => 'img/logo.svg',
        ],
        'favicon' => [
            'title' => 'Favicon',
            'description' => 'Browser tab icon (ICO or PNG format)',
            'format' => 'ICO, PNG',
            'path' => 'favicon.ico',
        ],
    ];

    /**
     * Returns the label for this presenter, which is used in the UI to identify it.
     */
    public function label(): string
    {
        return 'System Images';
    }

    /**
     * Returns the title for this presenter, which is displayed in the UI as the main heading.
     */
    public function title(): string
    {
        $config = $this->getImageConfig($this->entity->name);

        return $config['title'] ?? ucfirst(str_replace('_', ' ', $this->entity->name));
    }

    /**
     * Returns the subtitle for this presenter, which provides additional context about the image.
     */
    public function subTitle(): string
    {
        $format = $this->currentFormat();
        $statusBadge = $this->statusBadge();

        // Only show format and status, not description
        return $format.' • '.$statusBadge['text'];
    }

    /**
     * Get the description from database or config fallback
     */
    public function description(): string
    {
        // Use description from database if available
        if (! empty($this->entity->description)) {
            return $this->entity->description;
        }

        // Fallback to config description
        $config = $this->getImageConfig($this->entity->name);

        return $config['description'] ?? 'Custom system image';
    }

    /**
     * Returns the URL for this presenter, which is used to link to the image's edit page.
     */
    public function url(): string
    {
        return route('platform.customization.systemimages.edit', urlencode($this->entity->name));
    }

    /**
     * Returns the URL for the system image.
     * Uses current uploaded image or falls back to default path.
     */
    public function image(): ?string
    {
        // Use uploaded image if available
        if ($this->entity->file_path) {
            return asset($this->entity->file_path).'?v='.time();
        }

        // Fallback to default image path
        $config = $this->getImageConfig($this->entity->name);
        $defaultPath = $config['path'] ?? '';

        if ($defaultPath && file_exists(public_path($defaultPath))) {
            return asset($defaultPath);
        }

        // Final fallback to a placeholder
        return asset('img/placeholder-image.svg');
    }

    /**
     * Custom avatar rendering for persona with rounded corners instead of circle
     */
    public function avatar(): string
    {
        $imagePath = $this->image();

        return "<img src=\"{$imagePath}\" alt=\"{$this->entity->name}\" style=\"height: 32px; width: 32px; object-fit: contain;\" class=\"rounded me-2\">";
    }

    /**
     * Returns the number of models to return for a compact search result.
     * This method is used by the search functionality to display a list of matching results.
     */
    public function perSearchShow(): int
    {
        return 5;
    }

    /**
     * Returns a Laravel Scout builder object that can be used to search for matching images.
     * This method is used by the search functionality to retrieve a list of matching results.
     */
    public function searchQuery(?string $query = null): Builder
    {
        return $this->entity->search($query);
    }

    /**
     * Get image configuration for a given image name
     */
    protected function getImageConfig(string $name): array
    {
        return $this->systemImages[$name] ?? [
            'title' => ucfirst(str_replace('_', ' ', $name)),
            'description' => 'Custom system image',
            'format' => 'Various',
            'path' => '',
        ];
    }

    /**
     * Returns formatted file size if available
     */
    public function fileSize(): ?string
    {
        if ($this->entity->file_path && file_exists(public_path($this->entity->file_path))) {
            $bytes = filesize(public_path($this->entity->file_path));
            $units = ['B', 'KB', 'MB', 'GB'];

            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }

            return round($bytes, 2).' '.$units[$i];
        }

        return null;
    }

    /**
     * Returns image dimensions if available
     */
    public function dimensions(): ?string
    {
        if ($this->entity->file_path && file_exists(public_path($this->entity->file_path))) {
            $imagePath = public_path($this->entity->file_path);
            $imageInfo = getimagesize($imagePath);

            if ($imageInfo) {
                return $imageInfo[0].' × '.$imageInfo[1].' px';
            }
        }

        return null;
    }

    /**
     * Returns the current format/MIME type
     */
    public function currentFormat(): string
    {
        if ($this->entity->mime_type) {
            return strtoupper(str_replace(['image/', 'image/x-'], '', $this->entity->mime_type));
        }

        $config = $this->getImageConfig($this->entity->name);

        return $config['format'] ?? 'Unknown';
    }

    /**
     * Check if image is using default or custom version
     */
    public function isCustom(): bool
    {
        return ! empty($this->entity->file_path);
    }

    /**
     * Get status badge information
     */
    public function statusBadge(): array
    {
        if ($this->isCustom()) {
            return [
                'text' => 'Custom',
                'class' => 'bg-success',
            ];
        }

        return [
            'text' => 'Default',
            'class' => 'bg-secondary',
        ];
    }
}
