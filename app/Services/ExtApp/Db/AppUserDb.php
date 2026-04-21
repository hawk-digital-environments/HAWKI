<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Db;


use App\Models\ExtApp;
use App\Models\ExtAppUser;
use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

readonly class AppUserDb
{
    public function create(
        int                 $appId,
        int                 $userId,
        string              $passkey,
        AsymmetricPublicKey $publicKey,
        HybridCryptoValue   $privateKey,
        string              $extUserId,
        int                 $apiTokenId,
        HybridCryptoValue   $apiToken
    ): ExtAppUser
    {
        return ExtAppUser::create([
            'ext_app_id' => $appId,
            'user_id' => $userId,
            'passkey' => $passkey,
            'user_public_key' => $publicKey,
            'user_private_key' => $privateKey,
            'ext_user_id' => $extUserId,
            'personal_access_token_id' => $apiTokenId,
            'api_token' => $apiToken,
        ]);
    }
    
    /**
     * Tries to find an AppUser by its external ID.
     */
    public function findByExternalId(ExtApp $app, string $externalId): ?ExtAppUser
    {
        return ExtAppUser::query()
            ->where('ext_app_id', $app->id)
            ->where('ext_user_id', $externalId)
            ->first();
    }
    
    /**
     * Finds an AppUser by its associated PersonalAccessToken.
     */
    public function findByToken(PersonalAccessToken $token): ?ExtAppUser
    {
        return ExtAppUser::query()
            ->where('personal_access_token_id', $token->id)
            ->first();
    }
    
    /**
     * Finds all AppUsers for a given internal user ID.
     * This returns ALL AppUsers across all external applications.
     * @return Collection<ExtAppUser>
     */
    public function findByUserId(int $userId): Collection
    {
        return ExtAppUser::query()
            ->where('user_id', $userId)
            ->get();
    }
}
