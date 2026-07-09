<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat\Values;

use App\Services\Ai\Agents\Exceptions\InvalidToolTransferStringException;

readonly class ToolTransferData
{
    private const string TYPE_CAPABILITY = 'capability';
    private const string TYPE_TOOL = 'tool';

    public function __construct(
        private string     $type,
        public string      $transferString,
        public string      $toolOrCapability,
        public string|null $innerTool,
        public array       $settings
    )
    {
    }

    public function isCapability(): bool
    {
        return $this->type === self::TYPE_CAPABILITY;
    }

    public function isTool(): bool
    {
        return $this->type === self::TYPE_TOOL;
    }

    public static function fromString(string $toolTransferString): self
    {
        $parts = explode(':', $toolTransferString);
        $firstPart = $parts[0];

        $loadSettings = static function (string $settingsString): array {
            if (empty($settingsString)) {
                return [];
            }

            try {
                $settings = json_decode($settingsString, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($settings)) {
                    throw InvalidToolTransferStringException::forSettingsNotJsonObject($settingsString);
                }
                return $settings;
            } catch (\JsonException $e) {
                throw InvalidToolTransferStringException::forInvalidJsonSettings($settingsString, $e);
            }
        };

        if ($firstPart === 'capability') {
            $capabilityName = $parts[1] ?? null;
            $toolName = $parts[2] ?? null;
            $settingsString = implode(':', array_slice($parts, 3));

            if (empty($capabilityName) || empty($toolName)) {
                throw InvalidToolTransferStringException::forMissingCapabilityOrToolName($toolTransferString);
            }

            return new self(
                self::TYPE_CAPABILITY,
                $toolTransferString,
                $capabilityName,
                $toolName,
                $loadSettings($settingsString)
            );
        }

        $toolName = $firstPart;
        $settingsString = implode(':', array_slice($parts, 1));

        if (empty($toolName)) {
            throw InvalidToolTransferStringException::forEmptyToolName($toolTransferString);
        }

        return new self(
            self::TYPE_TOOL,
            $toolTransferString,
            $toolName,
            null,
            $loadSettings($settingsString)
        );
    }
}
