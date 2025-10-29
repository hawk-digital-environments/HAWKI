<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Db;


use App\Models\ExtApp;
use App\Models\User;
use Hawk\HawkiCrypto\Value\AsymmetricKeypair;
use Illuminate\Support\Collection;

readonly class AppDb
{
    public function createApp(
        User              $user,
        AsymmetricKeypair $keypair,
        string            $name,
        ?string           $url,
        string            $redirectUrl,
        ?string           $description,
        ?string           $logoUrl
    ): ExtApp
    {
        return ExtApp::create([
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
     * Returns all apps that are not deleted.
     * @return Collection<int, ExtApp>
     */
    public function findAll(): Collection
    {
        return ExtApp::query()
            ->where('deleted_at', null)
            ->get();
    }
    
    /**
     * Finds apps by their name.
     *
     * @param string $name
     * @return Collection<int, ExtApp>
     */
    public function findByName(string $name): Collection
    {
        return ExtApp::query()
            ->where('name', $name)
            ->where('deleted_at', null)
            ->get();
    }
    
    /**
     * Finds an app by the id of it's connected app user.
     * @param User $user
     * @return ExtApp|null
     */
    public function findByUser(User $user): ?ExtApp
    {
        return ExtApp::query()
            ->where('app_user_id', $user->id)
            ->where('deleted_at', null)
            ->first();
    }
    
    /**
     * Finds an app by its ID.
     *
     * @param int $id
     * @return ExtApp|null
     */
    public function findById(int $id): ?ExtApp
    {
        return ExtApp::query()
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();
    }
}
