<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AssistantAvatarController extends Controller
{
    use Actions\Destroy;
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\FetchRelated;
    use Actions\FetchRelationship;
    use Actions\Store;
    use Actions\Update;

    /**
     * Authorize reading an avatar against its owning assistant.
     */
    public function read(AssistantAvatar $model, $request): void
    {
        $this->authorize('view', $model->assistant);
    }

    /**
     * Authorize reading the parent assistant via the related link, so an avatar
     * cannot be used to leak a private assistant it belongs to.
     */
    public function readingRelatedAssistant(AssistantAvatar $model, $request): void
    {
        $this->authorize('view', $model->assistant);
    }

    /**
     * Authorize creating an avatar against its parent assistant (owner-only).
     */
    public function creating($request, $query): void
    {
        $assistant = Assistant::findOrFail(
            (int) request()->input('data.relationships.assistant.data.id'),
        );

        $this->authorize('update', $assistant);
    }

    /**
     * Authorize updating an avatar against its parent assistant (owner-only).
     */
    public function updating(AssistantAvatar $model, $request, $query): void
    {
        $this->authorize('update', $model->assistant);
    }

    /**
     * Authorize deleting an avatar against its parent assistant (owner-only).
     */
    public function deleting(AssistantAvatar $model, $request): void
    {
        $this->authorize('update', $model->assistant);
    }
}
