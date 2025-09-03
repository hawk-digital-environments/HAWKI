<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\AiFactory;
use App\Services\AI\Exception\NoContextBoundException;
use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use JsonSerializable;

class AiModel implements JsonSerializable
{
    private ?AiModelContext $context = null;
    
    public function __construct(
        /**
         * The raw configuration array for the model.
         */
        private readonly array $raw
    )
    {
    }

    /**
     * Checks if the model is active, meaning it is available for use.
     * @return bool
     */
    public function isActive(): bool
    {
        return (isset($this->raw['active']) && $this->raw['active'] === true) || !array_key_exists('active', $this->raw);
    }

    /**
     * Returns the configured ID of the model.
     * @return string
     */
    public function getId(): string
    {
        return $this->raw['id'] ?? '';
    }

    /**
     * Checks if the model ID matches the provided ID.
     * This is useful for checking if the model is the one we are looking for.
     * It will try a fuzzy match to check if the configured models ID ends with the provided ID or vis versa.
     *
     * @param string $idToTest The ID to test against the model's ID.
     * @return bool True if the model's ID matches the provided ID, false otherwise.
     */
    public function idMatches(string $idToTest): bool
    {
        $id = $this->getId();
        if (empty($id)) {
            return false;
        }
        return $id === $idToTest || str_ends_with($id, $idToTest) || str_ends_with($idToTest, $id);
    }

    /**
     * Returns the label of the model.
     * This is typically used for display purposes.
     * It will return the 'label' field if available, otherwise it will fall back to 'name' or 'id'.
     *
     * @return string The label of the model.
     */
    public function getLabel(): string
    {
        return $this->raw['label'] ?? $this->raw['name'] ?? $this->raw['id'] ?? '';
    }

    /**
     * Returns true if the model supports streaming responses.
     * Streaming allows the model to send partial responses as they are generated,
     * rather than waiting for the entire response to be generated before sending it.
     * @return bool
     */
    public function isStreamable(): bool
    {
        // Legacy compatibility check for 'streamable' field
        if (isset($this->raw['streamable']) && $this->raw['streamable'] === true) {
            return true;
        }
        // Otherwise use the new 'stream' tooling
        return $this->getTools()['stream'] ?? false;
    }

    /**
     * Returns the input methods supported by the model.
     * Input methods can include 'text', 'image', 'audio', etc.
     * @return string[]
     */
    public function getInputMethods(): array
    {
        return $this->raw['input'] ?? ['text'];
    }
    
    /**
     * Checks if the model supports a specific input method.
     * @param string $input The input method to check for.
     * @return bool
     * @see AiModel::getInputMethods() to see the list of supported input methods by the model.
     */
    public function hasInputMethod(string $input): bool
    {
        return in_array($input, $this->getInputMethods());
    }

    /**
     * Returns the output methods supported by the model.
     * Output methods can include 'text', 'image', 'audio', etc.
     * @return string[]
     */
    public function getOutputMethods(): array
    {
        return $this->raw['output'] ?? ['text'];
    }
    
    /**
     * Checks if the model supports a specific output method.
     * @param string $output The output method to check for.
     * @return bool
     * @see AiModel::getOutputMethods() to see the list of supported output methods by the model.
     */
    public function hasOutputMethod(string $output): bool
    {
        return in_array($output, $this->getOutputMethods());
    }

    /**
     * Returns a list of tools that the model can use.
     * Tools are typically used to extend the model's capabilities,
     * like RAG, MCP, or other specialized functions.
     * @return array
     */
    public function getTools(): array
    {
        return $this->raw['tools'] ?? [];
    }
    
    /**
     * Checks if the model has a specific tool enabled.
     * @param string $tool The tool to check for.
     * @return bool
     * @see AiModel::getTools() to see the list of supported tools by the model.
     */
    public function hasTool(string $tool): bool
    {
        $tools = $this->getTools();
        return array_key_exists($tool, $tools) && $tools[$tool] === true;
    }
    
    /**
     * Checks if this model possesses all required tools to process a document.
     * @return bool
     */
    public function canProcessDocument(): bool
    {
        return $this->hasTool('file_upload');
    }
    
    /**
     * Checks if this model possesses all required tools to process an image.
     * @return bool
     */
    public function canProcessImage(): bool
    {
        return $this->hasInputMethod('image') && $this->hasTool('vision');
    }
    
    /**
     * Checks if the model is available for the given usage type.
     * This is typically used to restrict certain models to specific usage contexts,
     * like internal use only or external applications.
     * @param ModelUsageType $usageType The usage type to check against.
     * @return bool True if the model is available for the given usage type, false otherwise.
     */
    public function isAvailableInUsageType(ModelUsageType $usageType): bool
    {
        if ($usageType === ModelUsageType::DEFAULT) {
            return true;
        }
        
        if ($usageType === ModelUsageType::EXTERNAL_APP && $this->isAllowedInExternalApp()) {
            return true;
        }
        
        return false;
    }

    /**
     * Checks if the model is allowed to be used in external applications.
     * This is typically used to restrict certain models to internal use only.
     * @return bool
     */
    public function isAllowedInExternalApp(): bool
    {
        return (isset($this->raw['external']) && $this->raw['external'] === true) || !array_key_exists('external', $this->raw);
    }
    
    /**
     * Returns the provider instance that manages this model.
     * This method will throw an exception if the model is not bound to a context.
     * @return ModelProviderInterface
     */
    public function getProvider(): ModelProviderInterface
    {
        $this->assertContextIsSet(__METHOD__);
        return $this->context->getProvider();
    }
    
    /**
     * Returns the current online status of the model.
     * This method will throw an exception if the model is not bound to a context.
     * @return ModelOnlineStatus
     */
    public function getStatus(): ModelOnlineStatus
    {
        $this->assertContextIsSet(__METHOD__);
        return $this->context->getStatus();
    }
    
    /**
     * Returns the client instance used to interact with the model's API.
     * This method will throw an exception if the model is not bound to a context.
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        $this->assertContextIsSet(__METHOD__);
        return $this->context->getClient();
    }
    
    public function toArray(): array
    {
        return $this->raw;
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    private function assertContextIsSet(string $method): void
    {
        if ($this->context === null) {
            throw new NoContextBoundException($this->getId(), $method);
        }
    }
    
    /**
     * Binds the given context to the model.
     * This method is intended for internal use only and will be called by {@see AiFactory::createModelWithContext()}
     * @param AiModel $model
     * @param AiModelContext $context
     * @return AiModel
     * @internal This method is intended for internal use only and will be called by {@see AiFactory::createModelWithContext()}
     */
    public static function bindContext(AiModel $model, AiModelContext $context): AiModel
    {
        $model->context = $context;
        return $model;
    }
}
