<?php
declare(strict_types=1);


namespace App\Services\ExtApp;

use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class ExtAppFeatureSwitch
{
    private ?bool $isEnabledCache = null;
    private ?bool $areAppsEnabledCache = null;
    private ?bool $isAiInGroupsEnabledCache = null;
    
    public function __construct(
        private readonly ExtAppContext $context,
        private readonly Repository    $config
    )
    {
    }
    
    /**
     * Returns true if the current request is made by an external application.
     * @return bool
     */
    public function isExtAppRequest(): bool
    {
        return $this->context->isExternal();
    }
    
    /**
     * Returns true if external access is enabled at all.
     * @return bool
     */
    public function isEnabled(): bool
    {
        if ($this->isEnabledCache === null) {
            $this->isEnabledCache = $this->config->get('external_access.enabled');
        }
        
        return $this->isEnabledCache;
    }
    
    /**
     * Returns true if external apps are enabled.
     * @return bool
     */
    public function areAppsEnabled(): bool
    {
        if ($this->areAppsEnabledCache === null) {
            $this->areAppsEnabledCache = $this->isEnabled()
                && $this->config->get('external_access.apps', false);
        }
        
        return $this->areAppsEnabledCache;
    }
    
    /**
     * Returns true if an external app is allowed to access AI in group chat.
     * Note: This implies the usage of AI in group chats! The private AI conversations are not affected by this setting.
     * @return bool
     */
    public function isAiInGroupsEnabled(): bool
    {
        if ($this->isAiInGroupsEnabledCache === null) {
            $this->isAiInGroupsEnabledCache = $this->areAppsEnabled()
                && $this->config->get('external_access.apps_groups_ai', false);
        }
        return $this->isAiInGroupsEnabledCache;
    }
}
