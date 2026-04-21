<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Announcements\AnnouncementService;
use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Room\RoomService;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Translation\Translator;

// use Illuminate\Support\Facades\View;

class HomeController extends Controller
{

    // Inject LanguageController instance
    public function __construct(
        private AiService  $aiService,
        private Translator $translator
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

        // get the first part of the path if there's a slug.
        $requestModule = explode('/', $request->path())[0];

        $hawki = User::findOrFail(1);

        $userData = [
            'avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($user))?->getUrl(),
            'hawki_avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($hawki))?->getUrl(),
            'convs' => $user->conversations()->with('messages')->get(),
            'rooms' => $user->rooms()->with('messages')->get(),
            'hawki_username' => $hawki->username,
        ];

        $activeModule = $requestModule;

        $activeOverlay = false;
        if (Session::get('last-route') && Session::get('last-route') != 'home') {
            $activeOverlay = true;
        }
        Session::put('last-route', 'home');

        $models = $this->aiService->getAvailableModels()->toArray();

        // Native capability flags describe the model itself; they are not user-selectable tools.
        $capabilityFlags = ['stream', 'file_upload', 'vision', 'tool_calling'];

        $toolKit = [];
        foreach ($models['models'] as $model) {
            if (!empty($model['capabilities']) && is_array($model['capabilities'])) {
                foreach ($model['capabilities'] as $capability => $value) {
                    if (in_array($capability, $capabilityFlags, true)) {
                        continue;
                    }
                    if ($value !== false && $value !== 'unsupported') {
                        $toolKit[] = $capability;
                    }
                }
            }
        }
        $toolKit = array_values(array_unique($toolKit));

        // Build capability → display label map.
        // Priority: 1) translation key  2) formatted capability name
        // Note: AiTool::description is the LLM-facing schema description, not a UI label.
        // @todo why are we building this twice? I am fairly certain I saw the exact same code in the js.
        $toolKitLabels = [];
        foreach ($toolKit as $capability) {
            $toolKitLabels[$capability] = $this->translator->has('Tool_' . $capability)
                ? $this->translator->get('Tool_' . $capability)
                : ucwords(str_replace('_', ' ', $capability));
        }
        $announcements = $announcementService->getUserAnnouncements();

        // Pass translation, authenticationMethod, and authForms to the view
        return view('modules.' . $requestModule,
            compact(
                'slug',
                'user',
                'userData',
                'activeModule',
                'activeOverlay',
                'models',
                'toolKit',
                'toolKitLabels',
                'announcements',
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

        $user = Auth::user();

        $userData = [
            'avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($user))?->getUrl(),
            'hawki_avatar_url' => $avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar(User::find(1)))?->getUrl(),
        ];

        $models = $this->aiService->getAvailableModels()->toArray();

        $activeModule = $module;
        return view('layouts.print_template',
            compact(
                'chatData',
                'activeModule',
                'user',
                'userData',
                'models'));

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
