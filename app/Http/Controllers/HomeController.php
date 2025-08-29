<?php

namespace App\Http\Controllers;

use App\Http\Controllers\LanguageController;

use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Room\RoomService;
use App\Services\Storage\AvatarStorageService;
use App\Services\System\SettingsService;
use App\Services\Announcements\AnnouncementService;

use Illuminate\Http\Request;
// use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\AI\AIConnectionService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    protected $languageController;
    protected $aiConnService;

    // Inject LanguageController instance
    public function __construct(LanguageController $languageController,
                                AIConnectionService $aiConnService)
    {
        $this->languageController = $languageController;
        $this->aiConnService = $aiConnService;
    }

    /// Redirects user to Home Layout
    /// Home layout can be chat, groupchat, or any other main module
    /// Propper rendering attributes will be send accordingly to the front end
    public function index(Request $request, AvatarStorageService $avatarStorage, $slug = null): View{

        $user = Auth::user();


        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService())->render();


        // get the first part of the path if there's a slug.
        $requestModule = explode('/', $request->path())[0];

        $avatarUrl = $user->avatar_id !== '' ? $avatarStorage->getFileUrl('profile_avatars', $user->username, $user->avatar_id) : null;
        $hawkiAvatarUrl = $avatarStorage->getFileUrl('profile_avatars', User::find(1)->username, User::find(1)->avatar_id);

        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
            'convs' => $user->conversations()->with('messages')->get(),
            'rooms' => $user->rooms()->with('messages')->get(),
        ];

        $activeModule = $requestModule;

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'home'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'home');


        $models = $this->aiConnService->getAvailableModels();

        $announcementService = new AnnouncementService();
        $announcements = $announcementService->getUserAnnouncements();


        // Pass translation, authenticationMethod, and authForms to the view
        return view('modules.' . $requestModule,
                    compact('translation',
                            'settingsPanel',
                            'slug',
                            'user',
                            'userData',
                            'activeModule',
                            'activeOverlay',
                            'models',
                            'announcements'
                        ));
    }

    public function print($module, $slug, AiConvService $aiConvService, RoomService $roomService, AvatarStorageService $avatarStorage){

        switch($module){
            case 'chat':
                $chatData = $aiConvService->load($slug);
            break;
            case 'groupchat':
                $chatData = $roomService->load($slug);
            break;
            default:
                response()->json(['error' => 'Module not valid!'], 404);
            break;
        }

        $user = Auth::user();
        $avatarUrl = $user->avatar_id !== '' ? $avatarStorage->getFileUrl('profile_avatars', $user->username, $user->avatar_id) : null;
        $hawkiAvatarUrl = $avatarStorage->getFileUrl('profile_avatars', User::find(1)->username, User::find(1)->avatar_id);

        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
        ];


        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsService())->render();
        $models = $this->aiConnService->getAvailableModels();

        $activeModule = $module;
        return view('layouts.print_template',
                compact('translation',
                        'settingsPanel',
                        'chatData',
                        'activeModule',
                        'user',
                        'userData',
                        'models'));

    }


    public function CheckSessionTimeout(): JsonResponse{
        if ((time() - Session::get('lastActivity')) > (config('session.lifetime') * 60))
        {
            return response()->json(['expired' => true]);
        }
        else{
            $remainingTime = (config('session.lifetime') * 60) - (time() - Session::get('lastActivity'));
            return response()->json([
                'expired' => false,
                'remaining'=>$remainingTime
            ]);
        }
    }


    public function dataprotectionIndex(Request $request): View{
        $translation = $this->languageController->getTranslation();
        return view('layouts.dataprotection', compact('translation'));
    }
}

