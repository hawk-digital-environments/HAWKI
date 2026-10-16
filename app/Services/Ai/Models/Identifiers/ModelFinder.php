<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Identifiers;


use App\Models\Ai\AiModel;
use App\Services\Ai\Exceptions\AmbiguousModelIdException;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

/**
 * Resolves the model references that clients send (e.g. the `model` of a chat request payload
 * or a system model in the config) to an {@see AiModel}.
 *
 * Accepted forms:
 * - The provider-qualified identifier `<provider_id>/<model_id>` (see {@see AiModelIdentifier}).
 * - A numeric primary key.
 * - A plain `model_id` (legacy UI, external apps, stored messages, config). It is only resolved
 *   when exactly one provider offers it, otherwise an {@see AmbiguousModelIdException} is thrown.
 *
 * A qualified identifier whose prefix is not a known provider, or that does not match a model of
 * that provider, is retried as a plain `model_id`, because model ids may contain the separator.
 *
 * @api
 */
readonly class ModelFinder
{
    public function __construct(
        private AiProviderRepository $providerRepository,
        private AiModelRepository    $modelRepository
    )
    {
    }

    /**
     * Returns the model for the given reference or null when no model matches.
     *
     * @param ScopeOverrides|null $scopeOverrides Applied to the model query. When given, the provider is
     *                                            looked up without contextual scopes as well (e.g. admin/CLI),
     *                                            otherwise with the scopes of the current user.
     * @throws AmbiguousModelIdException
     */
    public function find(string|int $reference, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        if (is_int($reference) || is_numeric($reference)) {
            return $this->modelRepository->findOne($reference, $scopeOverrides);
        }

        $identifier = AiModelIdentifier::fromString($reference);
        if ($identifier->isQualified()) {
            // Only bypass the provider scopes when the caller bypasses the model scopes as well,
            // regular users are not allowed to disable them (403)
            $provider = $this->providerRepository->findOneByProviderId(
                $identifier->providerId,
                $scopeOverrides === null ? null : $this->providerRepository->makeScopeOverrides()
            );
            if ($provider) {
                $model = $this->modelRepository->findOneByProvider($provider, $identifier->modelId, $scopeOverrides);
                if ($model) {
                    return $model;
                }
            }
        }

        $models = $this->modelRepository->findAllByModelId($reference, $scopeOverrides);
        if ($models->count() > 1) {
            throw AmbiguousModelIdException::forModelId($reference, $models->map->getIdentifier()->all());
        }

        return $models->first();
    }

    /**
     * Like {@see find()} but throws {@see ModelIdNotAvailableException} when no model matches.
     *
     * @throws AmbiguousModelIdException
     * @throws ModelIdNotAvailableException
     */
    public function findOrFail(string|int $reference, ?ScopeOverrides $scopeOverrides = null): AiModel
    {
        $model = $this->find($reference, $scopeOverrides);
        if (!$model) {
            throw ModelIdNotAvailableException::forModelId($reference);
        }
        return $model;
    }
}
