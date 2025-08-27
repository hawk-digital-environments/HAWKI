<?php

namespace App\Orchid\Traits;

trait ApiFormatColorTrait
{
    /**
     * Get consistent badge color for API format based on ID
     *
     * @param int $apiFormatId
     * @return string
     */
    protected function getApiFormatBadgeColor(int $apiFormatId): string
    {
        // Define consistent color palette across the system
        $colors = [
            'success',    // Green
            'info',       // Light blue
            'warning',    // Yellow
            'danger',     // Red
            'primary',    // Blue
            'dark',       // Dark
            'secondary',  // Gray
        ];
        
        // Generate consistent color based on API format ID
        $colorIndex = $apiFormatId % count($colors);
        return $colors[$colorIndex];
    }

    /**
     * Generate badge HTML for API format
     *
     * @param \App\Models\ApiFormat|null $apiFormat
     * @param string $additionalClasses
     * @return string
     */
    protected function getApiFormatBadge($apiFormat, string $additionalClasses = ''): string
    {
        if (!$apiFormat) {
            return '<span class="badge bg-secondary ' . $additionalClasses . '">Not Set</span>';
        }

        $badgeColor = $this->getApiFormatBadgeColor($apiFormat->id);
        return '<span class="badge bg-' . $badgeColor . ' ' . $additionalClasses . '">' . e($apiFormat->display_name) . '</span>';
    }
}
