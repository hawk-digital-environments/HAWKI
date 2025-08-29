<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');

        // Search in the database for users matching the query
        $users = User::where(
            static function (Builder $b) use ($query) {
                $b->where('name', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->whereNot('employeetype', 'app')
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
