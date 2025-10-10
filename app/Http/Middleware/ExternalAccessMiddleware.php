<?php

namespace App\Http\Middleware;

use App\Events\ExternalAccessFeatureToggleEvent;
use App\Services\ExtApp\ExtAppFeatureSwitch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ExternalAccessMiddleware
{
    private const MESSAGE_MAP = [
        'default' => 'External communication is not allowed. Please contact the administration for more information.',
        'apps' => 'External apps are not allowed. Please contact the administration for more information.',
        'apps_groups_ai' => 'External apps are not allowed to use AI in chats. Please contact the administration for more information.',
    ];
    
    public function __construct(
        private ExtAppFeatureSwitch $featureSwitch
    )
    {
    }

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            $result = match ($feature) {
                'apps' => $this->featureSwitch->areAppsEnabled(),
                'apps_groups_ai' => $this->featureSwitch->isAiInGroupsEnabled(),
                default => $this->featureSwitch->isEnabled(),
            };
            
            if (!$result) {
                return response()->json([
                    'response' => self::MESSAGE_MAP[$feature] ?? self::MESSAGE_MAP['default']
                ], 403);
            }
        }
        
        ExternalAccessFeatureToggleEvent::dispatch($features);
        
        return $next($request);
    }
}
