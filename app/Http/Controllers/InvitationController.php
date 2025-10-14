<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use App\Models\Invitation;

use App\Jobs\SendEmailJob;

use Illuminate\Http\Request;

use App\Http\Controllers\RoomController;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


class InvitationController extends Controller
{

    /// Send the email with the signed URL to external invitee
    public function sendExternInvitationEmail(Request $request) {

        // Validate the request
        $validatedData = $request->validate([
            'username' => 'required|string|max:255',
            'hash' => 'required|string|size:32',
            'slug' => 'required|string',
        ]);

        // Find the user based on username
        $user = User::where('username', $validatedData['username'])->first();

        // Check if the user exists
        if (!$user) {
            return response()->json(['error' => 'user not found']);
        }

        // Generate a signed URL with the hash
        $url = URL::signedRoute('open.invitation', [
            'tempHash' => $validatedData['hash'],
            'slug' => $validatedData['slug']
        ], now()->addHours(48));

        // Prepare email data
        $emailData = [
            'user' => $user,
            'title' => 'A Generic Email',
            'message' => 'YOU HAVE BEEN INVITED A THE NEW GROUP...',
            'url' => $url,  // Include the generated URL if needed in the email
        ];

        // Specify the view template and subject line
        $viewTemplate = 'emails.invitation';
        $subjectLine = 'Invitation';

        // Dispatch the email job
        SendEmailJob::dispatch($emailData, $user->email, $subjectLine, $viewTemplate)
                    ->onQueue('emails');

        return response()->json(['message' => 'Invitation email sent successfully.']);
    }


    /// store invitation on the database
    public function storeInvitations(Request $request, $slug) {
        $roomId = Room::where('slug', $slug)->firstOrFail()->id;
        $invitations = $request->input('invitations');

        foreach($invitations as $inv) {
            // Check if an invitation already exists for this user in this room
            $existingInvitation = Invitation::where('room_id', $roomId)
                                             ->where('username', $inv['username'])
                                             ->first();

            if ($existingInvitation) {
                // Update the existing invitation
                $existingInvitation->update([
                    'role' => $inv['role'],
                    'iv' => $inv['iv'],
                    'tag' => $inv['tag'],
                    'invitation' => $inv['encryptedRoomKey']
                ]);
            } else {
                // Create a new invitation
                Invitation::create([
                    'room_id' => $roomId,
                    'username' => $inv['username'],  // Use array notation
                    'role' => $inv['role'],
                    'iv' => $inv['iv'],
                    'tag' => $inv['tag'],
                    'invitation' => $inv['encryptedRoomKey']
                ]);
            }
        }
    }


    /// Open Signed link to external user
    /// user should be first redirected to registration
    /// after registration the decryption process should start
    public function openExternInvitation(Request $request, $tempHash, $slug) {
        // Get the expiration timestamp from the request
        $expires = $request->query('expires');

        // Check if the route has expired
        if ($expires && now()->timestamp > $expires) {
            return response()->json(['error' => 'The invitation link has expired.'], 403);
        }


        $invTempLink = json_encode(['tempHash' => $tempHash, 'slug' => $slug]);
        Session::put('invitation_tempLink', $invTempLink);
        return redirect('/login');
    }


    /// returns user's invitations when logging in
    public function getUserInvitations(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve all invitations with related room details
        $invitations = $user->invitations()->get();

        // Map the invitations to the desired format
        $formattedInvitations = $invitations->map(function($invitation) {
            return [
                'room_slug'   => $invitation->room->slug,
                'role'        => $invitation->role,
                'iv'          => $invitation->iv,
                'tag'          => $invitation->tag,
                'invitation'  => $invitation->invitation,
                'invitation_id' => $invitation->id
            ];
        });



        return response()->json([
            'formattedInvitations'=>$formattedInvitations]
        );
    }


    /// return invitation with the specific slug.
    /// thought for external invitation opening. (check groupchat functions)
    /// NOTE: Not finished yet
    public function getInvitationWithSlug(Request $request, $slug)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the invitation where the room's slug matches and the invitation belongs to the authenticated user
        $invitation = $user->invitations()
            ->whereRelation('room', 'slug', $slug)
            ->with('room') // Eager load the room
            ->first();

        // Check if the invitation exists
        if (!$invitation) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        // Format the invitation details
        $formattedInvitation = [
            'room_slug'     => $invitation->room->slug,
            'role'          => $invitation->role,
            'iv'            => $invitation->iv,
            'tag'           => $invitation->tag,
            'invitation'    => $invitation->invitation,
            'invitation_id' => $invitation->id
        ];

        return response()->json($formattedInvitation);
    }

     /// Accept invitation at finishing invitation handling (check groupchat functions)
    public function onAcceptInvitation(Request $request){

        // Validate the request to ensure invitation_id is present
        $validated = $request->validate([
            'invitation_id' => 'required|exists:invitations,id',
        ]);

        $user = Auth::user();

        $invitation = Invitation::findOrFail($request->input('invitation_id'));

        // Verify that the invitation is meant for the authenticated user
        if ($invitation->username !== $user->username) {
            return response()->json(['error' => 'Unauthorized to accept this invitation'], 403);
        }

        // Add the user to the room (assuming you have a pivot table for room members)
        $room = $invitation->room;

        $room->addMember($user->id, $invitation->role);


        // Delete or mark the invitation as accepted
        $invitation->delete();

        return response()->json([
            'success' => true,
            'room' => $room,
        ]);

    }

}
