<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantUpdated;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSettingValue;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Support\Facades\Event;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantSettingValueController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use Actions\Update;

    /**
     * Authorize reading a setting value against its parent assistant.
     */
    public function read(AssistantSettingValue $model, $request): void
    {
        $this->authorize('viewAssistantSettingValues', $model->assistant);
    }

    /**
     * Authorize creating a setting value against its parent assistant.
     */
    public function creating($request, $query): void
    {
        $assistant = Assistant::findOrFail(
            (int) request()->input('data.relationships.assistant.data.id'),
        );

        $this->authorize('update', $assistant);
    }

    /**
     * Authorize updating a setting value against its parent assistant.
     */
    public function updating(AssistantSettingValue $model, $request, $query): void
    {
        $this->authorize('update', $model->assistant);
    }

    /**
     * Authorize deleting a setting value against its parent assistant.
     */
    public function deleting(AssistantSettingValue $model, $request): void
    {
        $this->authorize('update', $model->assistant);
    }

    /**
     * After create/update, record a version bump for non-draft/private assistants.
     */
    public function saved(AssistantSettingValue $model, $request, $query): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * After delete, record a version bump for non-draft/private assistants.
     */
    public function deleted(AssistantSettingValue $model, $request): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * Dispatch an AssistantUpdated event for organizational/federated assistants
     * so setting changes are reflected in the version history.
     */
    private function recordUpdate(Assistant $assistant): void
    {
        $guardedStages = [ReleaseStage::DRAFT->value, ReleaseStage::PRIVATE->value];

        if (in_array($assistant->release_stage, $guardedStages, true)) {
            return;
        }

        Event::dispatch(new AssistantUpdated($assistant, null, ['setting_values']));
    }
}
