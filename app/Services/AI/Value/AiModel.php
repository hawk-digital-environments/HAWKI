<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use JsonSerializable;

class AiModel implements \ArrayAccess, JsonSerializable
{
    public function __construct(private array $raw)
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
     * Returns the output methods supported by the model.
     * Output methods can include 'text', 'image', 'audio', etc.
     * @return string[]
     */
    public function getOutputMethods(): array
    {
        return $this->raw['output'] ?? ['text'];
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
     * Checks if the model is allowed to be used in external applications.
     * This is typically used to restrict certain models to internal use only.
     * @return bool
     */
    public function isAllowedInExternalApp(): bool
    {
        return (isset($this->raw['external']) && $this->raw['external'] === true) || !array_key_exists('external', $this->raw);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->raw);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($this->offsetExists($offset)) {
            return $this->raw[$offset];
        }
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->raw[] = $value;
        } else {
            $this->raw[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->raw[$offset]);
        }
    }

    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}
