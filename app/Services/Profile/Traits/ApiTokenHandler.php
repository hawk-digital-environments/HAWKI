<?php

namespace App\Services\Profile\Traits;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use laravel\Sanctum\NewAccessToken;

trait ApiTokenHandler{

    public function createApiToken(string $name): NewAccessToken{
        $user = Auth::user();
        return $user->createToken($name);
    }


    public function fetchTokenList(){
        $user = Auth::user();
        // Retrieve all tokens associated with the authenticated user
        $tokens = $user->tokens()->get();
        // Construct an array of token data
        return $tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
            ];
        });
    }


    public function revokeToken(int $tokenId){
        try{
            $user = Auth::user();
            $token = $user->tokens()->where('id', $tokenId);
            $token->delete();
        }
        catch(Exception $e){
            Log::error($e->getMessage());
            throw $e;
        }

    }

}
