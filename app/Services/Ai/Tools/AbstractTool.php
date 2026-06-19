<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Models\Ai\AiModel;
use App\Services\Ai\Contracts\ToolInterface;
use NeuronAI\Tools\Tool;

/**
 * Under the hood we are using the Neuron framework, this class is just an adapter.
 * @see https://docs.neuron-ai.dev/agent/tools#custom-tools on how to write tools
 * The only difference is, that you can use the "__construct" method for dependency injection,
 * like you would do in any other Laravel class, and you can define the "name" and "description"
 * of the tool in separate methods, which are then passed to the parent constructor.
 */
abstract class AbstractTool extends Tool implements ToolInterface
{
    /**
     * This MUST be false, because we want to check if the constructor of the tool was called
     * or if someone forgot to call it when using dependency injection. If the constructor was not called,
     * the name and description of the tool would be null, which would cause errors when the model tries to use the tool.
     * @noinspection PhpUnusedFieldDefaultValueInspection
     * @noinspection PropertyInitializationFlawsInspection
     */
    private bool $constructorCalled = false;

    public function __construct()
    {
        parent::__construct($this->name(), $this->description());
        $this->constructorCalled = true;
    }

    /**
     * Must return the unique name of this tool, e.g. "get_current_weather".
     * Where possible I would suggest using a "private namespace" for your tools, e.g. "my_app_get_current_weather",
     * to avoid name clashes with other tools.
     */
    abstract protected function name(): string;

    /**
     * Must return the description of this tool, e.g. "Get the current weather in a given location".
     * This is provided to the model when it needs to decide which tool to use, so it should be as descriptive as possible about what the tool does and how to use it.
     */
    abstract protected function description(): string;

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
    public function getName(): string
    {
        $this->assertConstructorWasCalled();
        return parent::getName();
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): ?string
    {
        $this->assertConstructorWasCalled();
        return parent::getDescription();
    }

    /**
     * @inheritDoc
     */
    final public function execute(): void
    {
        $this->assertConstructorWasCalled();
        parent::execute();
    }

    /**
     * This method checks if the constructor of the tool was called before any of the parent methods are executed.
     * This is important because the name and description of the tool are set in the constructor, and if it wasn't called, these values would be null.
     * If the constructor was not called, an exception is thrown with a message indicating that the constructor must be called before executing the tool.
     */
    private function assertConstructorWasCalled(): void
    {
        if (!$this->constructorCalled) {
            throw new \LogicException('The constructor of the tool must be called before executing it. Please make sure to call parent::__construct() in the constructor of your tool.');
        }
    }
}
