<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Factories;

use App\Providers\AiServiceProvider;
use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Exceptions\VectorizerNotResolvedException;
use App\Services\Ai\Embeddings\Factories\Contracts\VectorizerFactoryInterface;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Utils\Lists\LazySingletonList;
use App\Utils\Lists\TopSortStringList;
use Illuminate\Container\Attributes\Give;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central registry that maps an {@see EmbeddingRequest} to the
 * {@see VectorizerInterface} capable of handling it — the embeddings counterpart of
 * {@see \App\Services\Ai\Chat\Factories\ChatAgentRegistry}.
 *
 * Factories are registered via {@see declare()} and iterated in topological order —
 * earlier factories take precedence. The first factory that returns a non-null
 * vectorizer from {@see VectorizerFactoryInterface::createVectorizer()} wins.
 *
 * The registry is a singleton whose factory registrations are wired up in
 * {@see AiServiceProvider} using `$app->extend(VectorizerRegistry::class, ...)`.
 *
 * @api
 */
#[Singleton()]
readonly class VectorizerRegistry
{
    /**
     * @var TopSortStringList<class-string<VectorizerFactoryInterface>>
     */
    private TopSortStringList $factoryClasses;

    public function __construct(
        /**
         * @var LazySingletonList<class-string<VectorizerFactoryInterface>, VectorizerFactoryInterface>
         */
        #[Give(AiServiceProvider::EMBEDDING_VECTORIZER_FACTORY_LIST)]
        private LazySingletonList $vectorizerFactories,
    ) {
        $this->factoryClasses = new TopSortStringList();
    }

    /**
     * Registers a vectorizer factory class with optional ordering constraints.
     *
     * @param class-string<VectorizerFactoryInterface> $factoryClass
     * @param null|class-string|list<class-string>     $before       factory classes this one must run before
     * @param null|class-string|list<class-string>     $after        factory classes this one must run after
     */
    public function declare(
        string $factoryClass,
        null|array|string $before = null,
        null|array|string $after = null,
    ): self {
        $this->factoryClasses->add($factoryClass, $before, $after);

        return $this;
    }

    /**
     * Iterates registered factories in priority order and returns the first vectorizer
     * produced, or null when no factory accepts the request.
     */
    public function tryToGetVectorizer(EmbeddingRequest $request): ?VectorizerInterface
    {
        foreach ($this->factoryClasses as $factoryClass) {
            $vectorizer = $this->vectorizerFactories->get($factoryClass)->createVectorizer($request);

            if (null !== $vectorizer) {
                return $vectorizer;
            }
        }

        return null;
    }

    /**
     * Returns the first vectorizer that accepts the request.
     *
     * @throws VectorizerNotResolvedException when no registered factory can handle the request
     */
    public function getVectorizer(EmbeddingRequest $request): VectorizerInterface
    {
        return $this->tryToGetVectorizer($request)
            ?? throw VectorizerNotResolvedException::forRequest($request->model);
    }
}
