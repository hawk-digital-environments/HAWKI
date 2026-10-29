<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Models\Member;
use App\Models\Room;
use App\Services\Chat\Room\Repositories\InvitationRepository;
use App\Services\Chat\Room\Repositories\RoomRepository;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    private const INVITABLE_ROLES = [Member::ROLE_ADMIN, Member::ROLE_EDITOR, Member::ROLE_VIEWER];

    public function __construct(
        private readonly RoomRepository       $roomRepository,
        private readonly InvitationRepository $invitationRepository,
        private readonly UserRepository       $userRepository
    )
    {
    }

    /// Send the email with the signed URL to external invitee
    public function sendExternInvitationEmail(Request $request)
    {

        // Validate the request
        $validatedData = $request->validate([
            'username' => 'required|string|max:255',
            'hash' => 'required|string|size:32',
            'slug' => 'required|string',
        ]);

        // Only room admins may send invitations
        $this->ensureRoomAdmin($validatedData['slug']);

        // Find the user based on username
        $user = $this->userRepository->findOneByUsername($validatedData['username']);

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
    public function storeInvitations(Request $request, $slug)
    {
        // Defense in depth: the route is already guarded by the roomAdmin middleware
        $room = $this->ensureRoomAdmin($slug);

        $validated = $request->validate([
            'invitations' => 'required|array|min:1',
            'invitations.*.username' => 'required|string|max:255',
            'invitations.*.role' => ['required', 'string', Rule::in(self::INVITABLE_ROLES)],
            'invitations.*.iv' => 'required|string',
            'invitations.*.tag' => 'required|string',
            'invitations.*.encryptedRoomKey' => 'required|string',
            'invitations.*.email' => 'sometimes|nullable|array',
        ]);
        $invitations = $validated['invitations'];

        foreach ($invitations as $inv) {
            // Users that are already active members must not be invited again;
            // role changes go through the admin-only changeMemberRole endpoint.
            $invitee = $this->userRepository->findOneByUsername($inv['username']);
            if ($invitee && $room->isMember($invitee->id)) {
                continue;
            }

            // Create a sub-request for sending the email if email data is present
            // This allows us to reuse the sendExternInvitationEmail method while avoiding
            // sending two requests from the client side.
            if (is_array($inv['email'] ?? null)) {
                $subRequest = new Request([
                    'username' => $inv['username'],
                    'hash' => $inv['email']['tempHash'] ?? '',
                    'slug' => $slug
                ]);

                $res = $this->sendExternInvitationEmail($subRequest);
                if (!empty($res->getData(true)['error'])) {
                    abort(400, $res->getData(true)['error'] ?? 'Failed to send invitation email.');
                }
            }

            // Create the invitation, or update it if one already exists for this user in this room
            $this->invitationRepository->createOrUpdate(
                room: $room,
                username: $inv['username'],
                role: $inv['role'],
                iv: $inv['iv'],
                tag: $inv['tag'],
                encryptedRoomKey: $inv['encryptedRoomKey']
            );
        }
    }


    /// Open Signed link to external user
    /// user should be first redirected to registration
    /// after registration the decryption process should start
    public function openExternInvitation(Request $request, $tempHash, $slug)
    {
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
        $invitations = $this->invitationRepository->findAllForUser($user);

        // Map the invitations to the desired format
        $formattedInvitations = $invitations->map(function ($invitation) {
            return [
                'room_slug' => $invitation->room->slug,
                'role' => $invitation->role,
                'iv' => $invitation->iv,
                'tag' => $invitation->tag,
                'invitation' => $invitation->invitation,
                'invitation_id' => $invitation->id
            ];
        });


        return response()->json([
                'formattedInvitations' => $formattedInvitations]
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
        $invitation = $this->invitationRepository->findOneForUserByRoomSlug($user, $slug);

        // Check if the invitation exists
        if (!$invitation) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        // Format the invitation details
        $formattedInvitation = [
            'room_slug' => $invitation->room->slug,
            'role' => $invitation->role,
            'iv' => $invitation->iv,
            'tag' => $invitation->tag,
            'invitation' => $invitation->invitation,
            'invitation_id' => $invitation->id
        ];

        return response()->json($formattedInvitation);
    }

    /// Accept invitation at finishing invitation handling (check groupchat functions)
    public function onAcceptInvitation(Request $request)
    {

        // Validate the request to ensure invitation_id is present
        $validated = $request->validate([
            'invitation_id' => 'required|exists:invitations,id',
        ]);

        $user = Auth::user();

        $invitation = $this->invitationRepository->findOne($validated['invitation_id']);
        if (!$invitation) {
            abort(404);
        }

        // Verify that the invitation is meant for the authenticated user
        if ($invitation->username !== $user->username) {
            return response()->json(['error' => 'Unauthorized to accept this invitation'], 403);
        }

        $room = $invitation->room;

        // Never use an invitation to change the role of an existing member;
        // role changes are restricted to the admin-only changeMemberRole endpoint.
        // Rejecting here also keeps the client from overwriting its valid room key
        // with the one contained in the invitation.
        if ($room->isMember($user->id)) {
            $invitation->delete();
            return response()->json(['error' => 'Already a member of this room'], 409);
        }

        if (!in_array($invitation->role, self::INVITABLE_ROLES, true)) {
            $invitation->delete();
            return response()->json(['error' => 'Invalid invitation'], 422);
        }

        $room->addMember($user->id, $invitation->role);


        // Delete or mark the invitation as accepted
        $invitation->delete();

        return response()->json([
            'success' => true,
            'room' => [...$room->toArray(), 'role' => $invitation->role],
        ]);

    }

    /// Resolves the room by slug and aborts unless the current user is an admin of it
    private function ensureRoomAdmin(string $slug): Room
    {
        // RoomAccessScope restricts the lookup to rooms the user is a member of
        $room = $this->roomRepository->findOneBySlugOrFail($slug);

        if (!$this->roomRepository->isMemberWithRole($room, Auth::user(), Member::ROLE_ADMIN)) {
            abort(403, 'Forbidden');
        }

        return $room;
    }
}
