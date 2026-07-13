<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantUpdated;
use App\Http\Controllers\Concerns\AuthorizesRelatedCreation;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantUserPrompt;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantUserPromptController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use AuthorizesRelatedCreation;

    /**
     * Authorize creation of a prompt against its parent assistant.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function creating($request, $query): void
    {
        $this->authorizeCreateAgainstRelatedModel('assistant', Assistant::class, 'update');
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
        $guardedStages = [AssistantReleaseStage::DRAFT->value, AssistantReleaseStage::PRIVATE->value];

        if (\in_array($assistant->release_stage, $guardedStages, true)) {
            return;
        }

        $assistant->load('assistantUserPrompts');
        Event::dispatch(new AssistantUpdated($assistant, null, ['assistant_user_prompts']));
    }
}
