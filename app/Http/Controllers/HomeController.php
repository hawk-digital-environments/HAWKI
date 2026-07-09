<?php

namespace App\Http\Controllers;

use App\Services\Announcements\AnnouncementService;
use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Room\RoomService;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

// use Illuminate\Support\Facades\View;

class HomeController extends Controller
{
    /// Redirects user to Home Layout
    /// Home layout can be chat, groupchat, or any other main module
    /// Propper rendering attributes will be send accordingly to the front end
    public function index(
        Request              $request,
        AvatarStorageService $avatarStorage,
        AnnouncementService  $announcementService,
                             $slug = null
    ): View
    {
        $user = Auth::user();

        // get the first part of the path if there's a slug.
        $requestModule = explode('/', $request->path())[0];

        $userData = [
            'convs' => $user->conversations()->with('messages')->get(),
            'rooms' => $user->rooms()->with('messages')->get()->map(function ($room) use ($user) {
                $member = $room->members()->where('user_id', $user->id)->first();

                $raw = $room->toArray();
                $raw['hasUnreadMessages'] = false;
                foreach ($room->messages as $message) {
                    if (!$message->isReadBy($member)) {
                        $raw['hasUnreadMessages'] = true;
                        break;
                    }
                }
                return $raw;
            })->toArray()
        ];

        $activeModule = $requestModule;

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'home') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'home');

        $announcements = $announcementService->getUserAnnouncements();

        // Pass translation, authenticationMethod, and authForms to the view
        return view('modules.' . $requestModule,
            compact(
                'slug',
                'userData',
                'activeModule',
                'activeOverlay',
                'announcements'
            ));
    }

    public function print($module, $slug, AiConvService $aiConvService, RoomService $roomService, AvatarStorageService $avatarStorage)
    {
        switch ($module) {
            case 'chat':
                $chatData = $aiConvService->load($slug);
                break;
            case 'groupchat':
                $chatData = $roomService->load($slug);
                break;
            default:
                return response()->json(['error' => 'Module not valid!'], 404);
        }

        $activeModule = $module;
        return view('layouts.print_template',
            compact(
                'chatData',
                'activeModule'
            ));

    }

    public function CheckSessionTimeout(): JsonResponse
    {
        if ((time() - Session::get('lastActivity')) > (config('session.lifetime') * 60)) {
            return response()->json(['expired' => true]);
        } else {
            $remainingTime = (config('session.lifetime') * 60) - (time() - Session::get('lastActivity'));
            return response()->json([
                'expired' => false,
                'remaining' => $remainingTime
            ]);
        }
    }

    public function dataprotectionIndex(Request $request): View
    {
        return view('layouts.dataprotection');
    }
}
