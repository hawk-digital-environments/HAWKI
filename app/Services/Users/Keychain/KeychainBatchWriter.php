<?php
declare(strict_types=1);


namespace App\Services\Users\Keychain;


use App\Models\User;
use App\Services\Users\Keychain\Repositories\UserKeychainRepository;
use App\Services\Users\Keychain\Value\KeychainBatch;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Container\Attributes\Singleton;

/**
 * @api
 *
 * Applies a {@see KeychainBatch} to a user's keychain.
 *
 * Two flows write a keychain: the `user-keychain-values/actions/batch-update` endpoint (passkey
 * change, room key exchange) and completing a registration. They must agree on the order of
 * operations — clean, then public key, then writes, then deletes — because a batch that both
 * cleans and sets means "replace", and a public key that lands after the values it belongs to
 * would leave the account undecryptable if the request died in between. Keeping that order in
 * one place is the reason this class exists.
 *
 * The writer performs no transaction of its own: the registration flow needs the whole
 * registration to be one transaction, and the batch-update endpoint is a single request that can
 * safely be retried.
 */
#[Singleton]
readonly class KeychainBatchWriter
{
    public function __construct(
        private UserKeychainRepository $keychainRepository,
        private UserRepository         $userRepository
    )
    {
    }

    public function write(User $user, KeychainBatch $batch): void
    {
        if ($batch->clean) {
            $this->keychainRepository->dropAllForUser($user);
        }

        if ($batch->publicKey !== null && $batch->publicKey !== '') {
            $this->userRepository->updatePublicKey($user, $batch->publicKey);
        }

        if ($batch->set !== []) {
            $this->keychainRepository->setValues($user, ...$batch->set);
        }

        if ($batch->remove !== []) {
            $this->keychainRepository->removeValues($user, ...$batch->remove);
        }
    }
}
