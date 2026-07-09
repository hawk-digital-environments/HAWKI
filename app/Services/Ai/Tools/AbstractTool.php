<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Services\Ai\Tools\Contracts\SettingsAwareToolInterface;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\Exceptions\ToolCallStateException;
use App\Services\System\Container\ServiceLocator;
use App\Utils\JsonSchema\JsonSchemaValidator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Base class for all HAWKI function-calling tools.
 *
 * Concrete subclasses implement the tool's behaviour in a `__invoke()` method whose
 * parameters are resolved from the validated argument map via the {@see ServiceLocator}.
 * Subclasses also provide {@see name()}, {@see description()}, and {@see schema()} as
 * required by {@see ToolInterface}.
 *
 * The `handle()` entry point called by the Laravel AI layer:
 *  - validates the incoming arguments against the schema returned by `schema()`,
 *  - enforces an optional per-instance call-count ceiling (`setMaxRuns`),
 *  - dispatches `__invoke()` with the validated args via the service locator
 *    (allowing the container to inject additional dependencies beyond the args),
 *  - converts the return value to a string (JSON-encoding arrays/objects), and
 *  - catches any exception, logging it and returning a structured error string to the AI.
 *
 * Example — a minimal concrete tool:
 * ```php
 * class GetCurrentWeatherTool extends AbstractTool
 * {
 *     public function name(): string { return 'get_current_weather'; }
 *     public function description(): string { return 'Returns the current weather for a city.'; }
 *
 *     public function schema(JsonSchema $schema): array
 *     {
 *         return ['city' => $schema->string()->required()];
 *     }
 *
 *     // Extra dependencies (WeatherClient) are injected by the container;
 *     // tool arguments (city) are resolved from the validated argument map.
 *     public function __invoke(WeatherClient $client, string $city): array
 *     {
 *         return $client->current($city);
 *     }
 * }
 * ```
 */
abstract class AbstractTool implements ToolInterface, SettingsAwareToolInterface
{
    private ServiceLocator|null $serviceLocator = null;
    private int $maxRuns = 0;
    private int $runs = 0;
    private array $settings = [];
    private Request|null $request = null;
    private array|null $arguments = null;

    /**
     * Injects a custom service locator, replacing the default container-backed singleton.
     * Primarily intended for testing, where a pre-configured locator with mock services is injected.
     */
    final public function setServiceLocator(ServiceLocator $serviceLocator): void
    {
        $this->serviceLocator = $serviceLocator;
    }

    protected function getServiceLocator(): ServiceLocator
    {
        return $this->serviceLocator ??= app(ServiceLocator::class);
    }

    /**
     * Limits how many times this tool instance may be invoked in a single agent session.
     * Set to 0 (the default) to allow unlimited calls.
     */
    protected function setMaxRuns(int $maxRuns): void
    {
        $this->maxRuns = $maxRuns;
    }

    protected function getMaxRuns(): int
    {
        return $this->maxRuns;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    protected function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Returns the raw Laravel AI {@see Request} that triggered the current invocation.
     * Only accessible while `__invoke()` is executing — throws outside that window.
     *
     * @throws ToolCallStateException when called outside of an active tool invocation.
     */
    protected function getRequest(): Request
    {
        if ($this->request === null) {
            throw ToolCallStateException::forRequestNotSet();
        }
        return $this->request;
    }

    /**
     * Returns the schema-validated argument map for the current invocation.
     * Only accessible while `__invoke()` is executing — throws outside that window.
     *
     * @throws ToolCallStateException when called outside of an active tool invocation.
     */
    protected function getArguments(): array
    {
        if ($this->arguments === null) {
            throw ToolCallStateException::forArgumentsNotSet();
        }
        return $this->arguments;
    }

    /**
     * Declares which HAWKI capability key this tool fulfils (e.g. `"web_search"`).
     * Returns null when the tool does not map to any well-known capability.
     * Agents and models use this to locate the right tool via {@see AiModel::$capabilities}.
     */
    public function capability(): string|null
    {
        return null;
    }

    /**
     * Entry point called by the Laravel AI layer on every tool invocation.
     *
     * Validates arguments, enforces the max-run ceiling, and delegates to `__invoke()`.
     * Any unhandled exception from `__invoke()` is caught, logged, and converted into
     * a structured error string that the AI model can understand and act on.
     */
    final public function handle(Request $request): Stringable|string
    {
        if (!is_callable([$this, '__invoke'])) {
            return $this->errorResponse('The tool implementation is broken! Do never call this tool again as it is currently not working!');
        }

        if ($this->maxRuns > 0 && $this->runs >= $this->maxRuns) {
            return $this->errorResponse("Reached its maximum number of runs ({$this->maxRuns})! Do not call this tool again!");
        }

        $args = $request->all();
        $validatedArgs = $this->validateArgumentsAgainstSchema($args);
        if (is_string($validatedArgs)) {
            return $validatedArgs;
        }

        try {
            $this->request = $request;
            $this->arguments = $validatedArgs;

            $result = $this->getServiceLocator()->call('tool.invokeChild', [$this, '__invoke'], $validatedArgs);

            if ($result instanceof Stringable || is_string($result)) {
                return $result;
            }

            if (is_callable([$result, 'text'])) {
                return $result->text();
            }

            return json_encode($result, JSON_THROW_ON_ERROR);

        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        } finally {
            $this->request = null;
            $this->arguments = null;
            $this->runs++;
        }
    }

    /**
     * Formats an error message for the AI model and logs it at `error` level.
     *
     * The returned string begins with `[ERROR]` so the model recognises it as a failure
     * and can decide not to retry. The current arguments and settings are included in the
     * log context for debugging; when a `Throwable` is passed the exception is also captured.
     */
    protected function errorResponse(string|\Throwable|null $message = null): string
    {
        $context = [
            'arguments' => $this->arguments,
            'settings' => $this->settings
        ];

        if ($message instanceof \Throwable) {
            $context['exception'] = $message;
            $message = $message->getMessage();
        }

        $error = "[ERROR] The tool {$this->name()} encountered an error: " . ($message ?? 'Unknown error');

        $this->getServiceLocator()->get(LoggerInterface::class)->error($error, $context);

        return $error;
    }

    private function validateArgumentsAgainstSchema(array $args): string|array
    {
        $schema = $this->schema(new JsonSchemaTypeFactory);
        if (empty($schema)) {
            return $args;
        }

        $result = (new JsonSchemaValidator())->validate($schema, $args);
        if (is_string($result)) {
            return $this->errorResponse('You provided invalid arguments for this tool: `' . $result . '`');
        }

        return $result;
    }
}
