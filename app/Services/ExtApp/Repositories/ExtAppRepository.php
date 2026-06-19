<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Repositories;


use App\Models\ExtApp;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Hawk\HawkiCrypto\Value\AsymmetricKeypair;
use Illuminate\Support\Collection;

class ExtAppRepository extends AbstractRepository
{
    public function create(
        User              $user,
        AsymmetricKeypair $keypair,
        string            $name,
        ?string           $url,
        string            $redirectUrl,
        ?string           $description,
        ?string           $logoUrl
    ): ExtApp
    {
        return $this->getQuery()->create([
            'name' => $name,
            'description' => $description,
            'logo_url' => $logoUrl,
            'app_public_key' => $keypair->publicKey,
            'url' => $url,
            'redirect_url' => $redirectUrl,
            'app_user_id' => $user->id,
        ]);
    }

    /**
     * Finds apps by their name.
     *
     * @param string $name
     * @return Collection<int, ExtApp>
     */
    public function findOneByName(string $name): Collection
    {
        return $this->getQuery()->where('name', $name)->get();
    }

    /**
     * Finds an app by the id of it's connected app user.
     * @param User $user
     * @return ExtApp|null
     */
    public function findOneByUser(User $user): ?ExtApp
    {
        return $this->getQuery()
            ->first();
    }
}
