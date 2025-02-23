<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Assuming you have a User model
use Adldap\Laravel\Facades\Adldap; // Assuming you're using Adldap2-Laravel for LDAP

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');

        // Search in the database for users matching the query
        $users = User::where('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->take(5) 
                    ->get();


        if (count($users) > 0) {
            return response()->json([
                'success' => true,
                'users' => $users, // Return a list of users
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No users found', // More appropriate message when no users are found
            ]);
        }
    }
}