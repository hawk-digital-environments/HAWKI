<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


trait MakesDisableNotAllowedCallbacksTrait
{
    public function makeDisableNotAllowedIgnore(): \Closure
    {
        return static fn() => false; // False -> continue -> Apply anyway
    }

    public function makeDisableNotAllowedThrowException(): \Closure
    {
        return static function (string $scopeKey, ModelScopeContext $context) {
            $modelClass = class_basename($context->modelClass);
            abort(403, "You can not disable scope: '{$scopeKey}' on '{$modelClass}' with this user or context.");
        };
    }

    public function makeDisableNotAllowedForceDisable(): \Closure
    {
        return static fn() => true; // True -> return before apply -> Do not apply the scope
    }
}
