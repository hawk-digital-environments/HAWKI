<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AiConvController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\InvitationController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use App\Services\AI\AIConnectionService;
use App\Models\User;


class HomeController extends Controller
{
    protected $languageController;

    // Inject LanguageController instance
    public function __construct(LanguageController $languageController, AIConnectionService $aiConnService)
    {
        $this->languageController = $languageController;
        $this->aiConnService = $aiConnService;
    }

    /// Redirects user to Home Layout
    /// Home layout can be chat, groupchat, or any other main module
    /// Propper rendering attributes will be send accordingly to the front end
    public function show(Request $request, $slug = null){

        $userProfile = Auth::user();


        // Call getTranslation method from LanguageController
        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsController())->initialize();

        // get the first part of the path if there's a slug.
        $requestModule = explode('/', $request->path())[0];

        $convController = new AiConvController();
        $convs = $convController->getUserConvs(request());

        $roomController = new RoomController();
        $rooms = $roomController->getUserRooms(request());

        $avatarUrl = $userProfile->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $userProfile->avatar_id) : null;
        $hawkiAvatarUrl = Storage::disk('public')->url('profile_avatars/' . User::find(1)->avatar_id);
        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
            'convs' => $convs,
            'rooms' => $rooms,
        ];


        $activeModule = $requestModule;

        $activeOverlay = false;
        if(Session::get('last-route') && Session::get('last-route') != 'home'){
            $activeOverlay = true;
        }
        Session::put('last-route', 'home');


        $models = $this->aiConnService->getAvailableModels();

        // Pass translation, authenticationMethod, and authForms to the view
        return view('modules.' . $requestModule,
                    compact('translation',
                            'settingsPanel',
                            'slug',
                            'userProfile',
                            'userData',
                            'activeModule',
                            'activeOverlay',
                            'models'));
    }

    public function print($module, $slug){

        switch($module){
            case('chat'):
                $controller = new AiConvController();
                $messages = $controller->loadConv($slug);
            break;
            case('groupchat'):
                $controller = new RoomController();
                $messages = $controller->loadRoom($slug);
            break;
            default:
                response()->json(['error' => 'Module not valid!'], 404);
            break;
        }

        $userProfile = Auth::user();
        $avatarUrl = $userProfile->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $userProfile->avatar_id) : null;
        $hawkiAvatarUrl = Storage::disk('public')->url('profile_avatars/' . User::find(1)->avatar_id);
        $userData = [
            'avatar_url'=> $avatarUrl,
            'hawki_avatar_url'=>$hawkiAvatarUrl,
        ];


        $translation = $this->languageController->getTranslation();
        $settingsPanel = (new SettingsController())->initialize();

        $models = $this->aiConnService->getAvailableModels();

        $activeModule = $module;
        return view('layouts.print_template',
                compact('translation',
                        'settingsPanel',
                        'messages',
                        'activeModule',
                        'userProfile',
                        'userData',
                        'models'));

    }


    public function CheckSessionTimeout(){
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


    public function dataprotectionIndex(Request $request){
        $translation = $this->languageController->getTranslation();
        return view('layouts.dataprotection', compact('translation'));
    }


    public function fetchOgMeta(Request $request)
    {
        $url = $request->query('url');
        // 1. URL sanity‑check
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL'], 400);
        }

        try {
            // 2. Download with a short timeout & friendly UA
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; LinkPreviewBot/1.0; +https://example.com/bot)'
            ])->timeout(8)->get($url);

            if (!$response->ok()) {
                return response()->json(['error' => 'Could not fetch URL'], 502);
            }

            $html = $response->body();

            // 3. Parse all meta tags once
            libxml_use_internal_errors(true);           // suppress broken‑HTML warnings
            $dom   = new \DOMDocument();
            $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//meta[@property or @name]');

            // 4. Extract what we care about
            $data = [
                'title'       => null,
                'description' => null,
                'image'       => null,
                'video'       => null,
                'audio'       => null,
            ];

            foreach ($nodes as $meta) {
                $prop = $meta->getAttribute('property') ?: $meta->getAttribute('name');
                $val  = $meta->getAttribute('content');

                switch (strtolower($prop)) {
                    case 'og:title':
                    case 'twitter:title':
                        $data['title'] = $data['title'] ?? $val;
                        break;

                    case 'og:description':
                    case 'twitter:description':
                        $data['description'] = $data['description'] ?? $val;
                        break;

                    case 'og:image':
                    case 'twitter:image':
                        $data['image'] = $data['image'] ?? $val;
                        break;

                    case 'og:video':
                    case 'twitter:player':
                        $data['video'] = $data['video'] ?? $val;
                        break;

                    case 'og:audio':
                        $data['audio'] = $data['audio'] ?? $val;
                        break;
                }
            }

            // 4 b. title fallback (plain <title> tag) if OG data missing
            if (!$data['title']) {
                $titleNode = $dom->getElementsByTagName('title')->item(0);
                $data['title'] = $titleNode ? trim($titleNode->textContent) : null;
            }

            // ✅ Find favicon or icon
            $iconNode = $xpath->query('//link[contains(@rel, "icon") or contains(@rel, "shortcut")]')->item(0);
            if ($iconNode) {
                $href = $iconNode->getAttribute('href');

                // Make absolute if it's relative
                if ($href && !preg_match('/^https?:\/\//i', $href)) {
                    $baseUrl = rtrim(parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST), '/');
                    $href = $baseUrl . '/' . ltrim($href, '/');
                }

                $data['favicon'] = $href;
            }



            return response()->json($data);
        } catch (\Throwable $e) {
            // Log::error($e);    // optionally keep a server log
            return response()->json(['error' => 'Failed to fetch'], 500);
        }
    }

}

