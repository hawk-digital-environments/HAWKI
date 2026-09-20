<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Values;

/**
 * Embeddings request IR — the domain counterpart of {@see \App\Services\Ai\Chat\Values\AiRequest}.
 *
 * Input is normalised to a list of strings by the formatter layer; the ordering of
 * {@see $input} is the ordering guarantee of the response ({@see EmbeddingResponse::$data}
 * is index-aligned to it).
 *
 * @api
 */
readonly class EmbeddingRequest
{
    /**
     * @param list<string>      $input          texts to vectorize, in response order
     * @param null|positive-int $dimensions     target vector dimensionality
     * @param null|string       $encodingFormat requested wire encoding ('float'); accepted
     *                                          for round-tripping, not interpreted further
     */
    public function __construct(
        public string $model,
        public array $input,
        public ?int $dimensions = null,
        public ?string $encodingFormat = null,
        public ?string $user = null,
        public ?string $formatKey = null,
    ) {
    }
}
