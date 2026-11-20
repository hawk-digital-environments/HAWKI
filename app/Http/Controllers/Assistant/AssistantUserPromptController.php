<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantUserPrompt;
use App\Services\Assistant\Events\AssistantUpdatedEvent;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantUserPromptController extends Controller
{
    use Actions\Destroy;
    use Actions\Store;

    public function __construct(private readonly EventDispatcher $events)
    {
    }

    /**
     * After creating the prompt, record a version bump for non-draft/private assistants.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function created(AssistantUserPrompt $model, $request, $query): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * After deleting the prompt, record a version bump for non-draft/private assistants.
     *
     * @param mixed $request
     */
    public function deleted(AssistantUserPrompt $model, $request): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * Dispatch an AssistantUpdated event for organizational/federated assistants
     * so prompt changes are reflected in the version history.
     */
    private function recordUpdate(Assistant $assistant): void
    {
        $guardedStages = [AssistantReleaseStage::DRAFT, AssistantReleaseStage::PRIVATE];

        if (\in_array($assistant->release_stage, $guardedStages, true)) {
            return;
        }

        $this->events->dispatch(new AssistantUpdatedEvent($assistant, ['assistant_user_prompts']));
    }
}
