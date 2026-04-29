<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantUpdated;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\UserPrompt;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class UserPromptController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;

    /**
     * Authorize reading the parent assistant via the related link, so a prompt
     * cannot be used to leak a private assistant it belongs to.
     */
    public function readingRelatedAssistant(UserPrompt $model, $request): void
    {
        $this->authorize('view', $model->assistant);
    }

    /**
     * Authorize creation of a prompt against its parent assistant.
     */
    public function creating($request, $query): void
    {
        $assistant = Assistant::findOrFail(
            (int) request()->input('data.relationships.assistant.data.id'),
        );

        $this->authorize('update', $assistant);
    }

    /**
     * After creating the prompt, record a version bump for non-draft/private assistants.
     */
    public function created(UserPrompt $model, $request, $query): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * Authorize deletion against the prompt's parent assistant.
     */
    public function deleting(UserPrompt $model, $request): void
    {
        $this->authorize('update', $model->assistant);
    }

    /**
     * After deleting the prompt, record a version bump for non-draft/private assistants.
     */
    public function deleted(UserPrompt $model, $request): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * Dispatch an AssistantUpdated event for organizational/federated assistants
     * so prompt changes are reflected in the version history.
     */
    private function recordUpdate(Assistant $assistant): void
    {
        $guardedStages = [ReleaseStage::DRAFT->value, ReleaseStage::PRIVATE->value];

        if (in_array($assistant->release_stage, $guardedStages, true)) {
            return;
        }

        $assistant->load('user_prompts');
        Event::dispatch(new AssistantUpdated($assistant, null, ['user_prompts']));
    }
}
