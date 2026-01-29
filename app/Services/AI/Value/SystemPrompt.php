<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\Translation\Value\Locale;

readonly class SystemPrompt implements \JsonSerializable, \Stringable
{
    public function __construct(
        public SystemPromptType $type,
        public Locale           $locale,
        public string           $text,
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => strtolower($this->type->name),
            'locale' => (string)$this->locale,
            'text' => $this->text,
        ];
    }
    
    public function __toString(): string
    {
        return $this->text;
    }
}
