<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class AccessTokenController extends Controller
{
    public function createToken(Request $request)
    {
        // Validate request data to ensure 'name' is provided and valid
        $validatedData = $request->validate([
            'name' => 'required|string|max:16',
        ]);
    
        // Retrieve the authenticated user
        $user = Auth::user();
        if (!$user) {
            // Return an error response if the user is not authenticated
            return response()->json(['error' => 'User not authenticated'], 401);
        }
    
        try {
            // Create a new token for the authenticated user
            $token = $user->createToken($validatedData['name']);
    
            // Return a JSON response with the new token
            return response()->json([
                'success' => true,
                'token' => $token->plainTextToken,
                'name' => $token->accessToken->name,
                'id' => $token->accessToken->id,
            ]);
        } catch (\Exception $exception) {
            // Log any exception that occurs during token creation
            Log::error('Token Creation Error', [
                'error' => $exception->getMessage(),
                'user_id' => $user->id,
            ]);
    
            // Return a JSON response indicating failure
            return response()->json([
                'success' => false,
                'error' => 'Failed to create token.',
            ], 500);
        }
    }

    public function fetchTokenList(Request $request)
    {
        $user = Auth::user();
        // Retrieve all tokens associated with the authenticated user
        $tokens = $user->tokens()->get();
        // Construct an array of token data
        $tokenList = $tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
            ];
        });
        // Return a JSON response with the token data
        return response()->json([
            'success' => true,
            'tokens' => $tokenList,
        ]);
    }



    public function revokeToken(Request $request)
    {
        // Validate request data with appropriate rules
        $validatedData = $request->validate([
            'tokenId' => 'required|integer',
        ]);
    
        // Attempt to retrieve the currently authenticated user
        try {
            $user = Auth::user();
    
            // Ensure the user is authenticated before proceeding
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }
    
            // Attempt to delete the token and capture the result
            $deleted = $user->tokens()->where('id', $validatedData['tokenId'])->delete();
    
            // Check if any row was actually deleted
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Token revoked successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not found.',
                ], 404);
            }
        } catch (\Exception $exception) {
            // Log the exception for debugging purposes
            Log::error('Token Revoke Error', [
                'error' => $exception->getMessage(),
                'user_id' => optional($user)->id,
                'token_id' => $validatedData['tokenId'],
            ]);
    
            // Return a JSON response indicating failure
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while attempting to revoke the token.',
            ], 500);
        }
    }

}
