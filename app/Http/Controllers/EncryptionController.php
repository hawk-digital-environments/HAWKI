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

    /// search for a list of requested user name and returns their public keys
    /// if user is not found on the DB it means that the user has not registered yet. in this case empty public key will be returned.
    public function requestUsersPublicKey(Request $request){

        $usernames = $request->get('usernames');

        $listOfUsers = [];
        foreach($usernames as $username){

            $user = User::where('username', $username)->first();
            if ($user && $user->publicKey) {
                // User has a public key
                $listOfUsers[] = [
                    'username' => $username,
                    'publicKey' => $user->publicKey,
                ];
            } else {
                // User has no public key
                $listOfUsers[] = [
                    'username' => $username,
                    'publicKey' => '',
                ];
            }
        }
        return $listOfUsers;
        return response()->json([
            'success' => true,
            'keys' => $listOfUsers
        ]);

    }



    /// encrypts using a symmetric key
    /// funciton specifically belongs to stream api to encrypt AI generated message with the received derived key from user.
    function encryptWithSymKey($encKey, $data, $isKey = false) {
        if ($encKey === null) {
            throw new Exception('Invalid encryption key.');
        }
    
        // Generate a 12-byte IV
        $iv = random_bytes(12);
    
        // Prepare the data for encryption (keep binary if it's a key)
        $encodedData = $isKey ? $data : $data;  // Remove utf8_encode
    
        // Check and ensure data is encoded in UTF-8
        if (!$isKey && (mb_detect_encoding($encodedData, 'UTF-8', true) !== 'UTF-8')) {
            $encodedData = mb_convert_encoding($encodedData, 'UTF-8');
        }
    
        // Encrypt using AES-256-GCM
        $ciphertext = openssl_encrypt($encodedData, 'aes-256-gcm', $encKey, OPENSSL_RAW_DATA, $iv, $tag);
    
        if ($ciphertext === false) {
            throw new Exception('Encryption failed.');
        }
    
        // Return Base64 encoded ciphertext, iv, and tag
        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($tag)
        ];
    }

    /// Decrypts using the symmetric key
    /// !!! The function is not used and is only written as a backup for later use. !!!!
    function decryptWithSymKey($jwk, $ciphertext, $iv, $tag, $isKey = false) {
        // Import key from JWK format
        $encKey = importKeyValueFromJWK($jwk);
        if ($encKey === null) {
            throw new Exception('Invalid encryption key.');
        }

        // Decode Base64-encoded data back to binary
        $ciphertext = base64_decode($ciphertext);
        $iv = base64_decode($iv);
        $tag = base64_decode($tag);

        // Decrypt the data
        $decryptedData = openssl_decrypt($ciphertext, 'aes-256-gcm', $encKey, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decryptedData === false) {
            throw new Exception('Decryption failed.');
        }

        // Return decrypted data (binary if it's a key)
        return $isKey ? $decryptedData : utf8_decode($decryptedData);
    }

    /// Convert JWK format to encryption Key
    public function importKeyValueFromJWK(array $jwk)
    {
        try {
            // Check if JWK has the required parameters
            if (!isset($jwk['kty'], $jwk['k'], $jwk['alg'])) {
                throw new InvalidArgumentException('Invalid JWK: missing required parameters.');
            }
    
            // Decode the base64url-encoded key 'k'
            $rawKey = $this->base64UrlDecode($jwk['k']);
    
            // Verify that the key is valid (optional but recommended)
            if (empty($rawKey)) {
                throw new Exception('Failed to decode the key.');
            }
    
            return $rawKey; // Return the raw key for further use
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Failed to import JWK: ' . $e->getMessage());
            
            // Return null to signal a failure in key import
            return null;
        }
    }
    
    private function base64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
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
