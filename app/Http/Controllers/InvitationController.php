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

        // Check if email notifications are enabled
        if (!config('hawki.send_groupchat_invitation_mails')) {
            return response()->json(['message' => 'Email notifications are disabled']);
        }

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
            return response()->json(['error' => 'user not found'], 404);
        }

        // Get room information
        $room = Room::where('slug', $validatedData['slug'])->first();
        if (!$room) {
            return response()->json(['error' => 'room not found'], 404);
        }

        // Get inviter (current authenticated user)
        $inviter = Auth::user();

        // Extract plain text description if encrypted
        $roomDescription = null;
        if ($room->room_description) {
            try {
                $descriptionData = json_decode($room->room_description, true);
                // If it's encrypted JSON, we can't decrypt it for email
                // So we'll skip the description in the email
                if (is_array($descriptionData) && isset($descriptionData['ciphertext'])) {
                    $roomDescription = null; // Encrypted, can't show in email
                } else {
                    $roomDescription = $room->room_description; // Plain text
                }
            } catch (\Exception $e) {
                $roomDescription = null;
            }
        }

        // Generate a signed URL with the hash
        $url = URL::signedRoute('open.invitation', [
            'tempHash' => $validatedData['hash'],
            'slug' => $validatedData['slug']
        ], now()->addHours(48));

        // Prepare email data with room and inviter information
        $emailData = [
            'user' => $user,
            'inviter_name' => $inviter->name,
            'room_name' => $room->room_name,
            'room_description' => $roomDescription,
            'url' => $url,
        ];

        // Specify the view template and subject line
        $viewTemplate = 'emails.invitation';
        $subjectLine = 'Invitation to ' . $room->room_name;

        // Dispatch the email job
        SendEmailJob::dispatch($emailData, $user->email, $subjectLine, $viewTemplate)
                    ->onQueue('emails');

        return response()->json(['message' => 'Invitation email sent successfully.']);
    }


    /// store invitation on the database
    public function storeInvitations(Request $request, $slug) {
        $room = Room::where('slug', $slug)->firstOrFail();
        $roomId = $room->id;
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
            
            // Broadcast invitation event to the invited user
            $eventData = [
                'type' => 'invitation',
                'room' => [
                    'slug' => $room->slug,
                    'room_name' => $room->room_name,
                    'room_icon' => $room->room_icon,
                    'invited_by' => Auth::user()->name,  // Current user is the inviter
                ]
            ];
            
            event(new \App\Events\RoomInvitationEvent($eventData, $inv['username']));
        }
    }

    public function convertTempHashInvitation(Request $request) {
        $validated = $request->validate([
            'room_slug' => 'required|string',
            'encrypted_room_key' => 'required|string',
            'role' => 'required|string|in:admin,editor,viewer'
        ]);

        $room = Room::where('slug', $validated['room_slug'])->firstOrFail();
        $user = Auth::user();

        // Check if user is already a member
        if ($room->isMember($user->id)) {
            // Delete old temp-hash invitation
            $oldInvitation = Invitation::where('room_id', $room->id)
                                        ->where('username', $user->username)
                                        ->first();
            
            if ($oldInvitation) {
                $oldInvitation->delete();
            }

            // Create new public-key invitation
            Invitation::create([
                'room_id' => $room->id,
                'username' => $user->username,
                'role' => $validated['role'],
                'iv' => '0',  // Public key encryption marker
                'tag' => '0',
                'invitation' => $validated['encrypted_room_key']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitation converted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User is not a member of this room'
        ], 400);
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

    /// Delete invitation
    public function deleteInvitation(Request $request, $slug){
        $room = Room::where('slug', $slug)->firstOrFail();
        $currentUser = Auth::user();

        // Check if username parameter is provided
        if ($request->has('username')) {
            // Admin/Moderator deleting someone else's invitation
            $validated = $request->validate([
                'username' => 'required|string|max:16',
            ]);

            // Check if user has permission (must be member of the room)
            if(!$room->isMember($currentUser->id)){
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $targetUsername = $validated['username'];
        } else {
            // User declining their own invitation
            $targetUsername = $currentUser->username;
        }

        $invitation = Invitation::where('room_id', $room->id)
                                ->where('username', $targetUsername)
                                ->first();

        if (!$invitation) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        // If declining own invitation, verify it belongs to current user
        if (!$request->has('username') && $invitation->username !== $currentUser->username) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invitation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invitation deleted successfully'
        ]);
    }

}

