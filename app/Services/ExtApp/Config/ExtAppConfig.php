<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class ExtAppConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * This setting enables or disables external API access to HAWKI models.
     * When set to "true", API requests through the external API endpoints are permitted. When set to "false",
     * all external API requests will be blocked. This is the master switch for API functionality.
     */
    public readonly bool $externalAccess;

    /**
     * If true, the creation of app tokens is allowed.
     * Currently, you are required to create an app manually through the CLI interface.
     * IMPORTANT: If you use this, ALLOW_EXTERNAL_COMMUNICATION must be set to true as well.
     * NOTE: If you use this it is HIGHLY recommended to set ALLOW_USER_TOKEN_CREATION to true as well.
     * Because this allows users to "remove" tokens created for external apps, and thus prevent them from accessing the API in the future.
     */
    public readonly bool $externalApps;

    /**
     * If true, group chats in external applications can use the "@hawki"(configureable) AI handle,
     * otherwise the chat only works like a normal chat without AI integration.
     * IMPORTANT: If you use this, ALLOW_EXTERNAL_CHAT as well as ALLOW_EXTERNAL_APPS must be set to true.
     */
    public readonly bool $aiInGroups;

    /**
     * The duration in seconds for which an app connection request is valid.
     * After this time has passed, the request will be considered invalid and the user will need to create a new request.
     * Default is 15 minutes (60 seconds * 15).
     */
    public readonly int $externalAppConnectRequestTimeout;

    /**
     * @inheritDoc
     */
    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'externalAccess' => (bool)$repo->get('external_access.enabled', false),
            'externalApps' => (bool)$repo->get('external_access.apps', false),
            'aiInGroups' => (bool)$repo->get('external_access.apps_groups_ai', false),
            'externalAppConnectRequestTimeout' => (int)$repo->get('external_access.app_connect_request_timeout', 60 * 15),
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'extApp';
    }

    /**
     * @inheritDoc
     */
    public function toPublicArray(Request $request): array|null
    {
        if (!$request->getUsageContext()->isExternalApp()) {
            return null;
        }

        $fullConfig = [
            'externalAccess' => $this->externalAccess,
            'externalApps' => $this->externalApps
        ];

        if ($request->user()) {
            $fullConfig['aiInGroups'] = $this->aiInGroups;
        }

        return $fullConfig;
    }
}
