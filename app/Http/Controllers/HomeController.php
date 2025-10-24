<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Announcements\AnnouncementService;
use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Room\RoomService;
use App\Services\FileConverter\FileConverterFactory;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\System\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

// use Illuminate\Support\Facades\View;

class HomeController extends Controller
{

    // Inject LanguageController instance
    public function __construct(
        private LanguageController $languageController,
        private AiService          $aiService
    )
    {
    }

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


        // Call getTranslation method from LanguageController with localized content
        $translation = $this->languageController->getTranslationWithLocalized();
        $settingsPanel = (new SettingsService())->render();


        // get the first part of the path if there's a slug.
        $requestModule = explode('/', $request->path())[0];

        $avatarUrl = !empty($user->avatar_id)
            ? $avatarStorage->getUrl($user->avatar_id, 'profile_avatars')
            : null;
        $hawkiAvatarUrl = $avatarStorage->getUrl(User::find(1)->avatar_id, 'profile_avatars');

        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
            'convs' => $user->conversations()->with('messages')->get(),
            'rooms' => $user->rooms()->with('messages')->get(),
            'hawki_username' => User::find(1)->username,
        ];

        $activeModule = $requestModule;

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'home'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'home');

        try {
            $models = $this->aiService->getAvailableModels()->toArray();
        } catch (\Exception $e) {
            // Log the error if logging trigger is enabled
            if (config('logging.triggers.default_model')) {
                \Log::error('Failed to load available models', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
            }
            // Provide empty models array as fallback
            $models = ['models' => []];
        }
        $models = $this->aiService->getAvailableModels()->toArray();
        $webSearchAvailable = false;

        foreach ($models['models'] as $model) {
            if (!empty($model['tools']['web_search'])) {
                $webSearchAvailable = true;
                break;
            }
        }

        $announcements = $announcementService->getUserAnnouncements();

        $converterActive = FileConverterFactory::converterActive();


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
                            'webSearchAvailable',
                            'announcements',
                            'converterActive',
                        ));
    }

    public function print($module, $slug, AiConvService $aiConvService, RoomService $roomService, AvatarStorageService $avatarStorage, SettingsService $settingsService)
    {

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
        $avatarUrl = !empty($user->avatar_id)
                    ? $avatarStorage->getUrl($user->avatar_id, 'profile_avatars')
                    : null;
        $hawkiAvatarUrl = $avatarStorage->getUrl(User::find(1)->avatar_id, 'profile_avatars');

        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
        ];


        $translation = $this->languageController->getTranslationWithLocalized();
        $settingsPanel = $settingsService->render();
        $models = $this->aiService->getAvailableModels()->toArray();

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


    public function dataprotectionIndex(Request $request): View
    {
        $translation = $this->languageController->getTranslationWithLocalizedContent();
        return view('layouts.dataprotection', compact('translation'));
    }

    public function accessibilityIndex(Request $request): View
    {
        $translation = $this->languageController->getTranslationWithLocalizedContent();
        return view('layouts.accessibility', compact('translation'));
    }

    public function imprintIndex(Request $request): View
    {
        $translation = $this->languageController->getTranslationWithLocalizedContent();
        return view('layouts.imprint', compact('translation'));
    }
}
