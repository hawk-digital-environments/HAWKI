<?php

namespace App\JsonApi\V1\PasskeyBackups;

use App\Services\Profile\Values\PasskeyBackupBlob;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property PasskeyBackupBlob $resource
 */
class PasskeyBackupResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return $this->resource->id;
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        // Only the client-encrypted blob leaves the server. The passkey itself and the recovery
        // code that decrypts this blob never touch the backend.
        return [
            'ciphertext' => $this->resource->secret->ciphertext,
            'iv' => $this->resource->secret->iv,
            'tag' => $this->resource->secret->tag,
        ];
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     */
    public function relationships($request): iterable
    {
        return [
        ];
    }
}
