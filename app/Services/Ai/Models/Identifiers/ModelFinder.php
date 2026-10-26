<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Identifiers;


use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\Exceptions\AmbiguousModelIdException;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

/**
 * Resolves model references (e.g. the `model` of a chat request payload) to an {@see AiModel}.
 *
 * Inside HAWKI a model is only referenced by its provider-qualified identifier
 * `<provider_id>/<model_id>` (see {@see AiModelIdentifier}) or by its numeric primary key,
 * which is what {@see find()} accepts.
 *
 * External surfaces (the external app API and the config files) still accept a plain `model_id`.
 * They resolve it with {@see findAllowingPlainModelId()} and continue with the qualified identifier.
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
     * Returns the model for a provider-qualified identifier or a numeric primary key,
     * or null when no model matches. A plain `model_id` never matches.
     *
     * @param ScopeOverrides|null $scopeOverrides Applied to the model query. When given, the provider is
     *                                            looked up without contextual scopes as well (e.g. admin/CLI),
     *                                            otherwise with the scopes of the current user.
     */
    public function find(string|int $reference, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        if (AiModelIdentifier::isNumericReference($reference)) {
            return $this->modelRepository->findOne((int)$reference, $scopeOverrides);
        }

        $identifier = AiModelIdentifier::fromString($reference);
        if (!$identifier->isQualified()) {
            return null;
        }

        // Only bypass the provider scopes when the caller bypasses the model scopes as well,
        // regular users are not allowed to disable them (403)
        $provider = $this->providerRepository->findOneByProviderId(
            $identifier->providerId,
            $scopeOverrides === null ? null : $this->providerRepository->makeScopeOverrides()
        );
        if (!$provider) {
            return null;
        }

        return $this->modelRepository->findOneByProvider($provider, $identifier->modelId, $scopeOverrides);
    }

    /**
     * Like {@see find()} but throws {@see ModelIdNotAvailableException} when no model matches.
     *
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

    /**
     * Like {@see find()}, but also resolves a plain `model_id` when exactly one provider offers it.
     * Only meant for external surfaces that have to keep accepting plain ids (external app API, config).
     *
     * @throws AmbiguousModelIdException when the plain `model_id` is offered by more than one provider.
     */
    public function findAllowingPlainModelId(string|int $reference, ?ScopeOverrides $scopeOverrides = null): AiModel|null
    {
        return $this->findAllowingPlain($reference, $scopeOverrides);
    }

    /**
     * Shared core of the plain-model-id lookups: a plain `model_id` resolves when exactly one model
     * offers it, otherwise {@see AmbiguousModelIdException} is thrown.
     */
    private function findAllowingPlain(string|int $reference, ?ScopeOverrides $scopeOverrides): AiModel|null
    {
        $model = $this->find($reference, $scopeOverrides);
        if ($model || AiModelIdentifier::isNumericReference($reference)) {
            return $model;
        }

        // Sorted by creation, so the candidates in the exception are ordered deterministically
        $models = $this->findAllByPlainModelId($reference, $scopeOverrides)->sortBy('id')->values();
        if ($models->count() > 1) {
            throw AmbiguousModelIdException::forModelId($reference, $models->map->getIdentifier()->all());
        }

        return $models->first();
    }

    /**
     * Returns all models offering the given plain `model_id`. Model ids may contain the separator
     * themselves, so a qualified-looking reference is tried as a plain id as well.
     */
    private function findAllByPlainModelId(string $reference, ?ScopeOverrides $scopeOverrides): AiModelCollection
    {
        return $this->modelRepository->findAllByModelId($reference, $scopeOverrides);
    }
}
