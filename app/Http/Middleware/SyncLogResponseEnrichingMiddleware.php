<?php

namespace App\Http\Middleware;

use App\Http\Resources\SyncLogEntryResource;
use App\Services\SyncLog\SyncLogTracker;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * This middleware is a helper for the reactive javascript framework that rely on the sync log.
 * The idea is, to modify all JSON responses and add a `_hawki_sync_log` field
 * containing any sync log entries that were created during the request.
 * While every app will receive the same entries using their websocket connection,
 * this allows them to immediately process the changes without waiting for the
 * websocket message to arrive; This allows for a more responsive user experience.
 */
readonly class SyncLogResponseEnrichingMiddleware
{
    public function __construct(
        private SyncLogTracker  $tracker,
        private LoggerInterface $logger
    )
    {
    }
    
    public function handle(Request $request, Closure $next)
    {
        $currentUser = $request->user();
        $resources = [];
        $response = $this->tracker->runWithResourceCollection(
            fn() => $next($request),
            $resources,
            fn(SyncLogEntryResource $entry) => $currentUser->id === $entry->getUserId()
        );
        
        // If there are no resources to add, return the original response as is
        if (empty($resources)) {
            return $response;
        }
        
        // If the response is not a JSON response, we cannot modify it
        if (!$response instanceof JsonResponse) {
            return $response;
        }
        
        try {
            $data = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
            $data['_hawki_sync_log'] = SyncLogEntryResource::collection($resources);
            $response->setData($data);
            return $response;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to decode JSON response content for SyncLog enrichment', ['exception' => $e]);
            return $response;
        }
    }
}
