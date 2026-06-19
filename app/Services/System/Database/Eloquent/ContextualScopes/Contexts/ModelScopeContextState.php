<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\ContextualScopes\Contexts;


class ModelScopeContextState
{
    public function __construct(
        public \Closure|null $defaultOnDisableNotAllowed = null,
        public bool          $allScopesLocallyDisabled = false,
        public \Closure|null $allScopesLocallyDisabledNotAllowed = null,
        /** @var array<string,\Closure|null> */
        public array         $disabledScopes = [],
    )
    {
    }

    public function clone(): self
    {
        return new self(
            $this->defaultOnDisableNotAllowed,
            $this->allScopesLocallyDisabled,
            $this->allScopesLocallyDisabledNotAllowed,
            $this->disabledScopes
        );
    }
}
