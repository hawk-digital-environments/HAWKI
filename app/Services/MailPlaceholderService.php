<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class MailPlaceholderService
{
    /**
     * Replace all placeholders in given text
     */
    public static function replacePlaceholders(string $text, array $customData = []): string
    {
        $placeholders = self::getStandardPlaceholders();

        // Merge custom data with standard placeholders (custom data takes precedence)
        $allPlaceholders = array_merge($placeholders, $customData);

        return str_replace(array_keys($allPlaceholders), array_values($allPlaceholders), $text);
    }

    /**
     * Get all standard placeholders
     */
    public static function getStandardPlaceholders(): array
    {
        return [
            '{{app_name}}' => Config::get('app.name', 'HAWKI'),
            '{{app_url}}' => Config::get('app.url', 'https://hawki.test'),
            '{{current_date}}' => Carbon::now()->format('Y-m-d'),
            '{{current_datetime}}' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get user-specific placeholders
     *
     * @param  \App\Models\User|null  $user
     */
    public static function getUserPlaceholders($user = null): array
    {
        if (! $user) {
            return [
                '{{user_name}}' => 'Guest User',
                '{{user_email}}' => 'guest@example.com',
            ];
        }

        return [
            '{{user_name}}' => $user->name ?? 'Unknown User',
            '{{user_email}}' => $user->email ?? '',
        ];
    }

    /**
     * Get all available placeholder descriptions for documentation
     */
    public static function getPlaceholderDescriptions(): array
    {
        return [
            '{{app_name}}' => 'Name of the application',
            '{{user_name}}' => 'Name of the user',
            '{{user_email}}' => 'User\'s email address',
            '{{app_url}}' => 'URL of the application',
            '{{current_date}}' => 'Current date (Y-m-d format)',
            '{{current_datetime}}' => 'Current date and time (Y-m-d H:i:s format)',

            // Template-specific placeholders
            '{{otp_code}}' => 'One-time password/authentication code',
            '{{invitation_link}}' => 'Link to join a group chat or room',
            '{{room_name}}' => 'Name of the chat room or group',
            '{{inviter_name}}' => 'Name of the person sending the invitation',
            '{{login_url}}' => 'Link to the login page',
            '{{dashboard_url}}' => 'Link to the admin dashboard',
        ];
    }

    /**
     * Get template-specific test data for testing purposes
     *
     * @param  \App\Models\User|null  $user
     */
    public static function getTestData(string $templateType, $user = null): array
    {
        $standardPlaceholders = self::getStandardPlaceholders();
        $userPlaceholders = self::getUserPlaceholders($user);

        // Template-specific test data
        $templateSpecificData = [
            'otp' => [
                '{{otp_code}}' => '123456',
            ],
            'invitation' => [
                '{{invitation_link}}' => Config::get('app.url').'/invitation/test-link',
                '{{room_name}}' => 'Test Group Chat',
                '{{inviter_name}}' => $user ? $user->name : 'Test Inviter',
            ],
            'welcome' => [
                '{{login_url}}' => Config::get('app.url').'/login',
                '{{dashboard_url}}' => Config::get('app.url').'/admin',
            ],
            'approval' => [
                '{{login_url}}' => Config::get('app.url').'/login',
                '{{dashboard_url}}' => Config::get('app.url').'/admin',
            ],
            'notification' => [
                '{{dashboard_url}}' => Config::get('app.url').'/admin',
            ],
        ];

        return array_merge(
            $standardPlaceholders,
            $userPlaceholders,
            $templateSpecificData[$templateType] ?? []
        );
    }
}
