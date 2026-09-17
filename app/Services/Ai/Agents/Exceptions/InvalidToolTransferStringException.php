<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

class InvalidToolTransferStringException extends \InvalidArgumentException implements AgentExceptionInterface
{
    public static function forNotAString(): self
    {
        return new self('Tool transfer strings must be an array of strings.');
    }

    public static function forInvalidType(string $transferString): self
    {
        return new self(sprintf(
            'Tool transfer string "%s" must describe either a capability or a tool name.',
            $transferString
        ));
    }

    public static function forCapabilityNotFound(string $capabilityKey): self
    {
        return new self(sprintf('Capability "%s" is not registered.', $capabilityKey));
    }

    public static function forCapabilityMissingInnerTool(string $capabilityKey): self
    {
        return new self(sprintf(
            'Capability "%s" requires an inner tool to be specified in the transfer string.',
            $capabilityKey
        ));
    }

    public static function forSettingsNotJsonObject(string $settingsString): self
    {
        return new self(sprintf(
            'Settings in tool transfer string must be a JSON object, got: "%s".',
            $settingsString
        ));
    }

    public static function forInvalidJsonSettings(string $settingsString, \JsonException $previous): self
    {
        return new self(
            sprintf('Invalid JSON settings in tool transfer string: "%s".', $settingsString),
            0,
            $previous
        );
    }

    public static function forMissingCapabilityOrToolName(string $transferString): self
    {
        return new self(sprintf(
            'Tool transfer string "%s" is missing the capability name or inner tool name.',
            $transferString
        ));
    }

    public static function forEmptyToolName(string $transferString): self
    {
        return new self(sprintf(
            'Tool transfer string "%s" does not contain a tool name.',
            $transferString
        ));
    }
}
