<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Services\System\Container\SystemEnvironment;
use Illuminate\Http\Request;

// @phpstan-ignore trait.unused
trait RequestAwareScopeTrait
{
    use ServiceLocatingScopeTrait;

    private bool $rast_isRunningInConsole;
    private \Closure $rast_onNoRequest;

    public function initializeRequestAwareScopeTrait(SystemEnvironment $environment): void
    {
        $this->rast_isRunningInConsole = $environment->runningInConsole();
        $this->rast_onNoRequest = static function () {
            abort(412, sprintf("No request instance found for applying scope: '%s'.", class_basename(static::class)));
        };
    }

    public function withOnNoRequest(\Closure $callback): static
    {
        $this->rast_onNoRequest = $callback;
        return $this;
    }

    protected function getRequest(): Request|null
    {
        if ($this->rast_isRunningInConsole) {
            return null;
        }
        // Use service locator, so we always get the latest request instance.
        return $this->serviceLocator->get(Request::class);
    }

    protected function runIfRequestPresent(
        \Closure           $callback,
        \Closure|true|null $callbackInCli = null
    ): mixed
    {
        $request = $this->getRequest();

        if ($request) {
            return $callback($request);
        }

        if ($callbackInCli && $this->rast_isRunningInConsole) {
            if ($callbackInCli === true) {
                return null;
            }

            return $callbackInCli();
        }

        return $this->serviceLocator->call('requestAwareScope.onNoRequest', $this->rast_onNoRequest);
    }
}
