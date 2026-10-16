<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Requests\Assistant\AssistantBuilderGuideRequest;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\BuilderGuide\AssistantBuilderGuideService;
use Illuminate\Http\JsonResponse;

/**
 * One turn of the builder's guide chat: an LLM that walks the creator
 * through the setup and proposes field values via structured output. The
 * result is not persisted here; the builder applies `updates` to its draft
 * and saves them through the regular update flow.
 */
class AssistantGuideTurnController
{
    public function __invoke(
        AssistantBuilderGuideRequest $request,
        AssistantBuilderGuideService $guide,
        Assistant $assistant,
    ): JsonResponse {
        $result = $guide->respond(
            $assistant,
            $request->draft(),
            $request->guideMessages(),
            app()->getLocale(),
            $request->avatarBackgrounds(),
        );

        // Cast so a reply that fills nothing still sends `updates` as `{}`,
        // not the `[]` an empty PHP array encodes to.
        return response()->json(['data' => [...$result, 'updates' => (object)$result['updates']]]);
    }
}
