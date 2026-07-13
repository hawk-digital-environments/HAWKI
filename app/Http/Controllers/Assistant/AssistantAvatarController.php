<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Concerns\AuthorizesRelatedCreation;
use App\Http\Controllers\Controller;
use App\Models\Assistants\Assistant;
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
    use AuthorizesRelatedCreation;

    public function creating($request, $query): void
    {
        $this->authorizeCreateAgainstRelatedModel('assistant', Assistant::class, 'update');
    }
}
