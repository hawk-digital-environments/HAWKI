<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Storage;

use App\Models\User;
use App\Services\System\UserTypes\UserContext;
use App\Services\Users\Exceptions\MissingAuthenticatedUserException;
use App\Services\Users\Repositories\UserSettingValueRepository;
use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * Database-backed storage for the settings of fully authenticated users.
 *
 * All queries receive the user **explicitly from the {@see UserContext}** — the filter
 * does not depend on the auth guard — and the repository additionally applies the
 * `'access'` contextual scope in request context as defense-in-depth (both filters agree
 * on the same user there; in CLI the scope self-disables, leaving the explicit filter).
 *
 * The storage is only selected by {@see \App\Services\Users\Settings\UserSettingsService}
 * when the user context resolves a fully authenticated {@see User}; accessing it any
 * other way throws.
 */
#[Singleton()]
class DatabaseUserSettingsStorage implements UserSettingsStorageInterface
{
    public function __construct(
        private readonly UserContext $userContext,
        private readonly UserSettingValueRepository $repository,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function loadRaw(string $namespace): array
    {
        return $this->repository->getRawRowsForUser($this->currentUser(), $namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function persistChanged(string $namespace, array $changed): void
    {
        $this->repository->upsertValuesForUser($this->currentUser(), $namespace, $changed);
    }

    /**
     * {@inheritDoc}
     */
    public function removeKeys(string $namespace, array $keys): void
    {
        $this->repository->deleteKeysForUser($this->currentUser(), $namespace, $keys);
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): string
    {
        return 'database:' . $this->currentUser()->id;
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaces(): array
    {
        return $this->repository->getNamespacesForUser($this->currentUser());
    }

    /**
     * {@inheritDoc}
     */
    public function inheritFrom(UserSettingsStorageInterface $source): void
    {
        $user = $this->currentUser();

        foreach ($source->getNamespaces() as $namespace) {
            $this->repository->upsertValuesForUser($user, $namespace, $source->loadRaw($namespace));
        }
    }

    /**
     * Returns the authenticated user from the user context — the same source the service
     * used to select this storage, so selection and execution cannot disagree.
     */
    private function currentUser(): User
    {
        return $this->userContext->getAuthenticatedUser()
            ?? throw MissingAuthenticatedUserException::forSettingsStorageOperation();
    }
}
