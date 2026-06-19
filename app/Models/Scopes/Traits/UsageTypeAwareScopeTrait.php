<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Services\System\UsageTypes\UsageContext;

trait UsageTypeAwareScopeTrait
{
    use ServiceLocatingScopeTrait;

    private \Closure $utast_usageTypeResolver;

    public function initializeUsageTypeAwareScopeTrait(UsageContext $usageContext): void
    {
        $this->utast_usageTypeResolver = static fn() => $usageContext->get();
    }

    public function withUsageTypeResolver(\Closure $resolver): self
    {
        $this->utast_usageTypeResolver = $resolver;
        return $this;
    }

    protected function getCurrentUsageType(): string
    {
        return $this->serviceLocator->call('usageTypeAwareScope.usageType', $this->utast_usageTypeResolver);
    }
}
