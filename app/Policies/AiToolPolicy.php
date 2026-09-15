<?php
declare(strict_types=1);


namespace App\Policies;


use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class AiToolPolicy
{
    public function view(?\App\Models\User $user, \App\Models\Ai\AiTool $tool): bool
    {
        return app(\App\Services\Ai\Tools\ToolAuthorization::class)->discoverable(\App\Models\Ai\AiTool::withoutGlobalScopes(), $user)->whereKey($tool->getKey())->exists();
    }

    public function viewModels(?\App\Models\User $user, \App\Models\Ai\AiTool $tool): bool
    {
        return $this->view($user, $tool);
    }

    public function viewServer(?\App\Models\User $user, \App\Models\Ai\AiTool $tool): bool
    {
        return $this->view($user, $tool);
    }

    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
}
