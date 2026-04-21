<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserKeychainValueResource;
use App\Models\PrivateUserData;
use App\Services\User\Keychain\Http\UserKeychainUpdateValuesRequest;
use App\Services\User\Keychain\UserKeychainDb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserKeychainController extends Controller
{
    public function __construct(
        private readonly UserKeychainDb $db
    )
    {
    }
    
    public function update(UserKeychainUpdateValuesRequest $request): JsonResponse
    {
        if ($request->isCleaning()) {
            $this->db->removeAllOfUser($request->user());
        }
        
        if ($request->hasNewPublicKey()) {
            $request->user()->update([
                'publicKey' => $request->getNewPublicKey()
            ]);
        }
        
        if ($request->hasSetList()) {
            $this->db->setValues(
                $request->user(),
                ...$request->getSetList()
            );
        }
        
        if ($request->hasRemoveList()) {
            $this->db->removeValues(
                $request->user(),
                ...$request->getRemoveList()
            );
        }
        
        return response()->json([
            'success' => true
        ]);
    }
    
    /**
     * In order to validate the given passkey, the client needs to fetch anything of the keychain
     * to decrypt it and verify the passkey is correct. This endpoint provides a value that is always
     * present in the keychain, either the public key (new system) or the old keychain data (legacy system).
     * If neither exists, a 404 is returned.
     * @param Request $request
     * @return JsonResponse
     */
    public function getPasskeyValidator(Request $request): JsonResponse
    {
        $publicKey = $this->db->findFirstPublicKeyOfUser($request->user());
        if ($publicKey) {
            $validatingValue = $publicKey->value;
        } else {
            $oldKeychain = $this->db->findLegacyKeychainOfUser($request->user());
            if (!$oldKeychain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No keychain data found'
                ], 404);
            }
            $validatingValue = $oldKeychain;
        }
        
        return response()->json([
            'success' => true,
            'validator' => $validatingValue
        ]);
    }
    
    /**
     * @deprecated This endpoint is used only to allow the legacy frontend to access all keychain values,
     *            in the future the keychain values will be accessed only via SyncLog.
     */
    public function list(Request $request): JsonResponse
    {
        return response()->json(
            UserKeychainValueResource::collection(
                $this->db->findAllOfUser($request->user())
            )
        );
    }
    
    /**
     * @deprecated Allows the frontend to fetch the old keychain data for migration purposes.
     *             This endpoint will be removed in the future.
     */
    public function getLegacyKeychain(Request $request): JsonResponse
    {
        $oldKeychain = $this->db->findLegacyKeychainOfUser($request->user());
        if (!$oldKeychain) {
            return response()->json([
                'success' => true,
                'message' => 'No legacy keychain data found'
            ], 204);
        }
        
        return response()->json([
            'success' => true,
            'keychain' => (string)$oldKeychain
        ]);
    }
    
    /**
     * @deprecated This is a temporary endpoint to mark that the user has migrated their keychain data
     *             to the new system. It will be removed in the future.
     */
    public function markAsMigrated(Request $request): JsonResponse
    {
        PrivateUserData::where('user_id', $request->user()->id)
            ->each(fn(PrivateUserData $data) => $data->delete());
        
        return response()->json([
            'success' => true
        ]);
    }
}
