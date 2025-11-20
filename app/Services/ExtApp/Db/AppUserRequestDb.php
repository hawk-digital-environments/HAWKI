<?php
declare(strict_types=1);


namespace App\Services\ExtApp\Db;


use App\Models\ExtAppUserRequest;
use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Database\Eloquent\Collection;

readonly class AppUserRequestDb
{
    /**
     * Create a new ExtAppUserRequest instance.
     *
     * @param int $appId
     * @param string $extUserId
     * @param AsymmetricPublicKey $publicKey
     * @param HybridCryptoValue $privateKey
     * @param string $requestId
     * @param \DateTimeInterface $validUntil
     * @return ExtAppUserRequest
     */
    public function create(
        int                 $appId,
        string              $extUserId,
        AsymmetricPublicKey $publicKey,
        HybridCryptoValue   $privateKey,
        string              $requestId,
        \DateTimeInterface  $validUntil
    ): ExtAppUserRequest
    {
        return ExtAppUserRequest::create([
            'ext_app_id' => $appId,
            'ext_user_id' => $extUserId,
            'user_public_key' => $publicKey,
            'user_private_key' => $privateKey,
            'request_id' => $requestId,
            'valid_until' => $validUntil
        ]);
    }
    
    /**
     * Find the first valid ExtAppUserRequest by its request ID.
     *
     * @param string $requestId
     * @return ExtAppUserRequest|null
     */
    public function findRequestById(string $requestId): ?ExtAppUserRequest
    {
        return ExtAppUserRequest::query()
            ->where('request_id', $requestId)
            ->where('valid_until', '>=', now())
            ->first();
    }
    
    /**
     * Finds all AppUserRequests that have timed out.
     * Meaning the "valid_until" date is in the past.
     * @return Collection<ExtAppUserRequest>
     */
    public function findTimedOut(): Collection
    {
        return ExtAppUserRequest::query()
            ->where('valid_until', '<', now())
            ->get();
    }
}
