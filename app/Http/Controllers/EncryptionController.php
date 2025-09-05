<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\PrivateUserData;

class EncryptionController extends Controller
{

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

    /// User sends a backup of the keychain after a new key is added to the keychain
    /// check out encryption.js for more information.
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


    /// Sends back user's encrypted keychain
    public function fetchUserKeychain(){

        $user = Auth::user();


        $prvUserData = PrivateUserData::where('user_id', $user->id)->first();
        $keychainData = json_encode([
            'keychain'=> $prvUserData->keychain,
            'KCIV'=> $prvUserData->KCIV,
            'KCTAG'=> $prvUserData->KCTAG,
        ]);

        return $keychainData;
    }

}
