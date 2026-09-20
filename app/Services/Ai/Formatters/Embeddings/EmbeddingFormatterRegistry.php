<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Embeddings;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Formatters\Embeddings\Contracts\EmbeddingFormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterNotFoundException;
use App\Services\Ai\Formatters\Exceptions\InvalidFormatterClassException;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps format keys (e.g. 'openai') to
 * {@see EmbeddingFormatterInterface} implementations.
 *
 * The embeddings counterpart of {@see \App\Services\Ai\Formatters\FormatterRegistry}.
 * Formatters are resolved from the container lazily on first {@see get()} for a key;
 * built-ins are registered in {@see AiServiceProvider}, plugins extend the registry
 * through `$app->extend(EmbeddingFormatterRegistry::class, ...)`.
 *
 * @api
 */
#[Singleton()]
class EmbeddingFormatterRegistry
{
    public const string DEFAULT_KEY = 'openai';

    /**
     * @var array<string, class-string<EmbeddingFormatterInterface>>
     */
    private array $formatterClasses = [];

    public function __construct(
        /**
         * Lazy singleton cache keyed by format key.
         *
         * @var LazySingletonList<string, EmbeddingFormatterInterface>
         */
        #[Give(AiServiceProvider::EMBEDDING_FORMATTER_LIST)]
        private readonly LazySingletonList $instances,
    ) {
    }

    /**
     * Registers a formatter class under a format key.
     *
     * @param string                                    $key            Unique format identifier (e.g. 'openai')
     * @param class-string<EmbeddingFormatterInterface> $formatterClass
     */
    public function declare(string $key, string $formatterClass): self
    {
        if (!is_a($formatterClass, EmbeddingFormatterInterface::class, true)) {
            throw InvalidFormatterClassException::forClassNotImplementingInterface($key, $formatterClass);
        }

        $this->formatterClasses[$key] = $formatterClass;

        return $this;
    }

    /**
     * Returns the formatter instance registered under the given key.
     *
     * @throws FormatterNotFoundException when no formatter is registered under $key
     */
    public function get(string $key): EmbeddingFormatterInterface
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
    public function resolve(?string $key = null): EmbeddingFormatterInterface
    {
        return $this->get($key ?? self::DEFAULT_KEY);
    }
}
