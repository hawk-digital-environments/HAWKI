<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTraitTestFixtures;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\MakesDisableNotAllowedCallbacksTrait;

class MakesDisableNotAllowedCallbacksProxy
{
    use MakesDisableNotAllowedCallbacksTrait;

    public function ignore(): \Closure
    {
        return $this->makeDisableNotAllowedIgnore();
    }

    public function throwException(): \Closure
    {
        return $this->makeDisableNotAllowedThrowException();
    }

    public function forceDisable(): \Closure
    {
        return $this->makeDisableNotAllowedForceDisable();
    }
}
