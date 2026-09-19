<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Formatters\Contracts\FormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterNotFoundException;
use App\Services\Ai\Formatters\Exceptions\InvalidFormatterClassException;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps format keys (e.g. 'openResponses') to
 * {@see FormatterInterface} implementations.
 *
 * Formatters are resolved from the container lazily on first {@see get()} for a key.
 * Built-in formatters are registered in {@see AiServiceProvider}; plugins extend the
 * registry through the same mechanism (`$app->extend(FormatterRegistry::class, ...)`).
 *
 * @api
 */
#[Singleton()]
class FormatterRegistry
{
    public const string DEFAULT_KEY = 'openResponses';

    /**
     * @var array<string, class-string<FormatterInterface>>
     */
    private array $formatterClasses = [];

    public function __construct(
        /**
         * Lazy singleton cache keyed by format key.
         *
         * @var LazySingletonList<string, FormatterInterface>
         */
        #[Give(AiServiceProvider::FORMATTER_LIST)]
        private readonly LazySingletonList $instances,
    ) {
    }

    /**
     * Registers a formatter class under a format key.
     *
     * @param string                           $key            Unique format identifier (e.g. 'openResponses').
     * @param class-string<FormatterInterface> $formatterClass
     */
    public function declare(string $key, string $formatterClass): self
    {
        if (!is_a($formatterClass, FormatterInterface::class, true)) {
            throw InvalidFormatterClassException::forClassNotImplementingInterface($key, $formatterClass);
        }

        $this->formatterClasses[$key] = $formatterClass;

        return $this;
    }

    /**
     * Returns the formatter instance registered under the given key.
     *
     * The instance is created by the container on first access and cached for subsequent calls.
     *
     * @throws FormatterNotFoundException when no formatter is registered under $key
     */
    public function get(string $key): FormatterInterface
    {
        if (!isset($this->formatterClasses[$key])) {
            throw FormatterNotFoundException::forKey($key);
        }

        return $this->instances->get($this->formatterClasses[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->formatterClasses[$key]);
    }

    /**
     * Resolves the formatter for an optionally given key, falling back to the default key.
     */
    public function resolve(?string $key = null): FormatterInterface
    {
        return $this->get($key ?? self::DEFAULT_KEY);
    }
}
