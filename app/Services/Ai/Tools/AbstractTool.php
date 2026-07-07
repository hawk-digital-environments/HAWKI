<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Services\Ai\Tools\Contracts\SettingsAwareToolInterface;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\System\Container\ServiceLocator;
use App\Utils\JsonSchema\JsonSchemaValidator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use Psr\Log\LoggerInterface;
use Stringable;


abstract class AbstractTool implements ToolInterface, SettingsAwareToolInterface
{
    private ServiceLocator|null $serviceLocator = null;
    private int $maxRuns = 0;
    private int $runs = 0;
    private array $settings = [];
    private Request|null $request = null;
    private array|null $arguments = null;

    final public function setServiceLocator(ServiceLocator $serviceLocator): void
    {
        $this->serviceLocator = $serviceLocator;
    }

    protected function getServiceLocator(): ServiceLocator
    {
        return $this->serviceLocator ??= app(ServiceLocator::class);
    }

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

    protected function getRequest(): Request
    {
        if ($this->request === null) {
            // @todo exception
            throw new \RuntimeException('Request is not set. This method can only be called during the execution of the tool.');
        }
        return $this->request;
    }

    protected function getArguments(): array
    {
        if ($this->arguments === null) {
            // @todo exception
            throw new \RuntimeException('Arguments are not set. This method can only be called during the execution of the tool.');
        }
        return $this->arguments;
    }

    /**
     * This method tells HAWKI, which "capability" this tool provides.
     * You can think of a capability as a "feature" or "functionality" that the tool offers.
     * If your model is linked to this tool you can use {@see AiModel::$capabilities} to check for it.
     * @return string|null
     */
    public function capability(): string|null
    {
        return null;
    }

    /**
     * @inheritDoc
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
