<?php
declare(strict_types=1);


namespace App\Http\Controllers;


use App\Services\Auth\Http\GuestUserRequest;
use App\Services\Users\Db\UserDb;
use Illuminate\Http\JsonResponse;

class LocalRegistrationController extends Controller
{
    /**
     * Submit guest access request
     * Creates a new local user account with submitted credentials
     */
    public function submitGuestRequest(
        GuestUserRequest $request,
        UserDb           $userDb
    ): JsonResponse
    {
        $user = $userDb->createUserFromGuestUserRequest(
            data: $request->getData()
        );

        if ($user) {
            return response()->json([
                'success' => true,
                'message' => 'Your guest access request has been submitted successfully. You can now log in with your credentials.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again.',
        ], 500);
    }
}
