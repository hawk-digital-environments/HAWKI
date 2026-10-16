<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Identifiers\Values;


use App\Models\Ai\AiModel;

/**
 * The public, provider-qualified identifier of an {@see AiModel}: `<provider_id>/<model_id>`,
 * e.g. `gwdg/gemma-4-31b-it`.
 *
 * The same `model_id` may be offered by several providers, so the `model_id` alone is not
 * unique. This class is the single place that builds and parses the qualified string;
 * resolving it to a database record is done by {@see \App\Services\Ai\Models\Identifiers\ModelFinder}.
 *
 * Parsing splits at the FIRST separator, because provider ids never contain one, while
 * model ids may (e.g. OpenRouter's `google/gemma-3`). Whether the prefix really is a known
 * provider can only be decided against the database, so a parsed instance is a candidate.
 *
 * @api
 */
final readonly class AiModelIdentifier implements \Stringable
{
    public const string SEPARATOR = '/';

    public function __construct(
        public string|null $providerId,
        public string      $modelId
    )
    {
    }

    /**
     * Builds the identifier of a stored model. Falls back to the plain `model_id` when the
     * provider relation cannot be resolved (e.g. it is hidden by a contextual scope).
     */
    public static function fromModel(AiModel $model): self
    {
        return new self($model->provider?->provider_id, $model->model_id);
    }

    /**
     * Parses a string into its provider and model part. A string without separator is a
     * plain (legacy) model id with a null provider.
     */
    public static function fromString(string $identifier): self
    {
        $parts = explode(self::SEPARATOR, $identifier, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return new self(null, $identifier);
        }

        return new self($parts[0], $parts[1]);
    }

    /**
     * True when this identifier carries a provider part.
     */
    public function isQualified(): bool
    {
        return $this->providerId !== null;
    }

    public function toString(): string
    {
        return $this->isQualified()
            ? $this->providerId . self::SEPARATOR . $this->modelId
            : $this->modelId;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
