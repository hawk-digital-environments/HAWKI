<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Events\AssistantUpdated;
use App\Http\Controllers\Concerns\AuthorizesRelatedCreation;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSettingValue;
use App\Services\Assistant\Values\AssistantReleaseStage;
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
    use AuthorizesRelatedCreation;

    /**
     * Authorize creating a setting value against its parent assistant.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function creating($request, $query): void
    {
        $this->authorizeCreateAgainstRelatedModel('assistant', Assistant::class, 'update');
    }

    /**
     * After create/update, record a version bump for non-draft/private assistants.
     *
     * @param mixed $request
     * @param mixed $query
     */
    public function saved(AssistantSettingValue $model, $request, $query): void
    {
        $this->recordUpdate($model->assistant);
    }

    /**
     * After delete, record a version bump for non-draft/private assistants.
     *
     * @param mixed $request
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
        $guardedStages = [AssistantReleaseStage::DRAFT->value, AssistantReleaseStage::PRIVATE->value];

        if (\in_array($assistant->release_stage, $guardedStages, true)) {
            return;
        }

        Event::dispatch(new AssistantUpdated($assistant, null, ['assistant_setting_values']));
    }
}
