<?php

namespace App\Http\Controllers;

use App\Services\Profile\ProfileService;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

use Exception;
use Illuminate\Support\Facades\Log;


class ProfileController extends Controller
{
    protected $profileService;
    public function __construct(ProfileService $profileService){
        $this->profileService = $profileService;
    }


    // SECTION: PROFILE INFORMATION
    public function update(Request $request): JsonResponse{

        $validatedData = $request->validate([
            'img' => 'string',
            'displayName' => 'string|max:20',
            'bio' => 'string|max:255',
        ]);

        $this->profileService->update($validatedData);
        return response()->json([
            'success' => true,
            'response' => 'User information updated'
        ]);
    }


    public function requestProfileReset(): JsonResponse|RedirectResponse{
        $this->profileService->resetProfile();
        return response()->redirectTo('/register');
    }



    // SECTION: PASSKEY BACKUP
    public function backupPassKey(Request $request): JsonResponse{

        $validatedData = $request->validate([
            'cipherText' => 'required|string',
            'tag' => 'required|string',
            'iv' => 'required|string',
        ]);

        $this->profileService->backupPassKey($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Backup Successfull!',
        ]);


    }

    public function requestPasskeyBackup(): JsonResponse{

        $response = $this->profileService->retrievePasskeyBackup();
        return response()->json([
            'success' => true,
            'passkeyBackup' => $response,
        ]);
    }


    // SECTION: API TOKENS

    public function requestApiToken(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:16',
        ]);
        try {
            $token = $this->profileService->createApiToken($validatedData['name']);
            // Return a JSON response with the new token
            return response()->json([
                'success' => true,
                'token' => $token->plainTextToken,
                'name' => $token->accessToken->name,
                'id' => $token->accessToken->id,
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }


    public function fetchTokenList(): JsonResponse
    {
        $tokenList = $this->profileService->fetchTokenList();
        // Return a JSON response with the token data
        return response()->json([
            'success' => true,
            'tokens' => $tokenList,
        ]);
    }



    public function revokeToken(Request $request): JsonResponse
    {
        // Validate request data with appropriate rules
        $validatedData = $request->validate([
            'tokenId' => 'required|integer',
        ]);

        $this->profileService->revokeToken($validatedData['tokenId']);

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.',
        ]);
    }




}
