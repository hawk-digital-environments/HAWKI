<?php

namespace App\Http\Controllers;

use App\Models\PrivateUserData;
use App\Services\Profile\ProfileService;
use App\Services\Profile\ApiTokenService;
use App\Services\Profile\PasskeyService;



use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class ProfileController extends Controller
{

    // SECTION: PROFILE INFORMATION
    public function update(Request $request, ProfileService $profileService): JsonResponse{

        $validatedData = $request->validate([
            'displayName' => 'string|max:20',
            'bio' => 'string|max:255',
        ]);

        $profileService->update($validatedData);
        return response()->json([
            'success' => true,
            'response' => 'User information updated'
        ]);
    }

    public function uploadAvatar(Request $request, ProfileService $profileService): JsonResponse
    {
        $validatedData = $request->validate([
            'image' => 'required|file|max:20480'
        ]);
        $url = $profileService->assignAvatar($validatedData['image']);
        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }


    public function requestProfileReset(ProfileService $profileService): JsonResponse|RedirectResponse{
        $profileService->resetProfile();
        return response()->redirectTo('/register');
    }

    public function validatePasskey(Request $request){
        $passkey = $request->getContent();

        $request->validate([
            'passkey' => 'string',
        ]);


        // Validate that passkey is not empty
        if (empty($passkey)) {
            return response()->json([
                'success' => false,
                'message' => 'Passkey cannot be empty'
            ]);
        }

        // Validate passkey pattern using the same regex as frontend
        if (!preg_match('/^[A-Za-z0-9!@#$%^&*()_+-]+$/', $passkey)) {
            return response()->json([
                'success' => false,
                'message' => 'Passkey contains invalid characters'
            ]);
        }

        // Additional validation checks could be added here
        // For example, minimum length requirements
        if (strlen($passkey) < 8) {
            return response()->json([
                'success' => false,
                'message' => 'Passkey must be at least 8 characters long'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Passkey is valid'
        ]);
    }


    // SECTION: PASSKEY BACKUP
    public function backupPassKey(Request $request, PasskeyService $passkeyService): JsonResponse{

        $validatedData = $request->validate([
            'cipherText' => 'required|string',
            'tag' => 'required|string',
            'iv' => 'required|string',
        ]);

        $passkeyService->backupPassKey($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Backup Successfull!',
        ]);


    }

    public function requestPasskeyBackup(PasskeyService $passkeyService): JsonResponse{

        $response = $passkeyService->retrievePasskeyBackup();
        return response()->json([
            'success' => true,
            'passkeyBackup' => $response,
        ]);
    }

    public function backupKeychain(Request $request){

        $validatedData = $request->validate([
            'ciphertext' => 'required|string',
            'iv' => 'required|string',
            'tag' => 'required|string',
        ]);


        $user = Auth::user();

        try{
            $privateUserData = PrivateUserData::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'KCIV' => $validatedData['iv'],
                    'KCTAG' => $validatedData['tag'],
                    'keychain' => $validatedData['ciphertext'],
                ]
            );

        } catch (\Exception $error) {
            return response()->json([
                'success' => false,
                'error' => $error->getMessage()
            ]);
        }


        return response()->json([
            'success' => true,
        ]);
    }

    /// Returns the requested salt to the user
    public function getServerSalt(Request $request)
    {
        // Get 'saltlabel' from the header
        $saltLabel = $request->header('saltlabel');

        // Check if the saltlabel header exists
        if (!$saltLabel) {
            return response()->json(['error' => 'saltlabel header is required'], 400);
        }

        $serverSalt = env(strtoupper($saltLabel));

        // Check if the salt exists
        if (!$serverSalt) {
            return response()->json(['error' => 'Salt not found'], 404);
        }

        // Send back the salt, base64-encoded
        return response()->json(['salt' => base64_encode($serverSalt)]);
    }


    // SECTION: API TOKENS

    public function requestApiToken(Request $request, ApiTokenService $apiTokenService): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:16',
        ]);
        try {
            $token = $apiTokenService->createApiToken($validatedData['name']);
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


    public function fetchTokenList(ApiTokenService $apiTokenService): JsonResponse
    {
        $tokenList = $apiTokenService->fetchTokenList();
        // Return a JSON response with the token data
        return response()->json([
            'success' => true,
            'tokens' => $tokenList,
        ]);
    }



    public function revokeToken(Request $request, ApiTokenService $apiTokenService): JsonResponse
    {
        // Validate request data with appropriate rules
        $validatedData = $request->validate([
            'tokenId' => 'required|integer',
        ]);

        $apiTokenService->revokeToken($validatedData['tokenId']);

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully.',
        ]);
    }
}
