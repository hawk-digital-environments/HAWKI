<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\LaravelAi;

use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use App\Services\Ai\Tools\ToolAuthorization;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Providers\Tools\ProviderTool;

/** Weak keys preserve provider-specific tool classes without retaining actors in a long-lived worker. */
#[Singleton]
final class NativeToolAuthorizations
{
    private \WeakMap $contexts;

    public function __construct()
    {
        $this->contexts = new \WeakMap();
    }

    public function register(ProviderTool $tool, string $capability, AgentRequestContext $context): ProviderTool
    {
        $this->contexts[$tool] = [$capability, \WeakReference::create($context)];
        return $tool;
    }

    public function authorize(array $tools): void
    {
        foreach ($tools as $tool) {
            if ($tool instanceof AuthorizedTool) {
                $tool->authorize();
            } elseif ($tool instanceof ProviderTool) {
                if (!isset($this->contexts[$tool])) {
                    throw ToolAccessException::denied();
                }
                [$capability, $reference] = $this->contexts[$tool];
                $context = $reference->get();
                if (!$context instanceof AgentRequestContext) {
                    throw ToolAccessException::denied();
                }
                app(ToolAuthorization::class)->authorizeNative($capability, $context);
            } else {
                throw ToolAccessException::denied();
            }
        }
    }
}
