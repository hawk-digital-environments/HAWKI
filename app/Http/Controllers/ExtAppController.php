<?php

namespace App\Http\Controllers;

use App\Models\ExtApp;
use App\Models\ExtAppUser;
use App\Models\ExtAppUserRequest;
use App\Services\ExtApp\AppUserRequestActionHandler;
use App\Services\ExtApp\AppUserRequestCreator;
use App\Services\ExtApp\AppUserRequestSessionStorage;
use App\Services\ExtApp\Db\AppDb;
use App\Services\ExtApp\Db\AppUserDb;
use App\Services\ExtApp\Db\AppUserRequestDb;
use App\Services\ExtApp\Value\AppUserRequestSessionValue;
use App\Services\Frontend\Connection\ConnectionFactory;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Psr\Log\LoggerInterface;

class ExtAppController extends Controller
{
    public function __construct(
        protected AppDb     $appDb,
        protected AppUserDb $appUserDb,
    )
    {
    }
    
    public function getConnection(
        Request           $request,
        ConnectionFactory $connectionFactory
    ): JsonResponse
    {
        $app = $this->resolveAppOrFail($request);
        $externalId = $this->resolveExtUserIdOrFail($request);
        $appUser = $this->resolveAppUserOrFail($app, $externalId);
        
        return response()->json($connectionFactory->createExtAppConnection($appUser));
    }
    
    public function createConnection(
        Request               $request,
        AppUserRequestDb      $appUserRequestDb,
        AppUserRequestCreator $requestCreator,
        ConnectionFactory $connectionFactory,
    ): JsonResponse
    {
        $app = $this->resolveAppOrFail($request);
        $externalId = $this->resolveExtUserIdOrFail($request);
        $appUserRequestDb->findTimedOut()->each(fn(ExtAppUserRequest $userRequest) => $userRequest->delete());
        if ($this->appUserDb->findByExternalId($app, $externalId)) {
            abort(400, 'There is already an active connection for this user.');
        }
        
        return response()->json(
            $connectionFactory->createExtAppRequestConnection(
                $requestCreator->create($app, $externalId)
            )
        );
    }
    
    public function receiveAppConnectRequest(
        Request                      $request,
        AppUserRequestDb             $appUserRequestDb,
        AppUserRequestSessionStorage $sessionStorage,
    ): View|RedirectResponse
    {
        $userRequest = $appUserRequestDb->findRequestById($request->route('request_id'));
        if (!$userRequest) {
            $sessionStorage->clear();
            return view('modules.apps.request_timeout');
        }
        
        $sessionStorage->store($userRequest);
        
        $userRequest->delete();
        
        return redirect(route('login'));
    }
    
    public function confirmAppConnectRequest(
        AppUserRequestSessionStorage $sessionStorage,
        AppUserRequestSessionValue   $userRequest,
        Request                      $request
    ): View
    {
        $app = $this->appDb->findById($userRequest->appId);
        if (!$app) {
            $sessionStorage->clear();
            return view('modules.apps.confirm_error');
        }
        
        $urlWithoutPath = static function (string $url): string {
            $parsedUrl = parse_url($url);
            $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
            return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $port;
        };
        
        return view('modules.apps.confirm', [
            'app_name' => $app->name,
            'user' => $request->user(),
            'url' => empty($app->url) ? $urlWithoutPath($app->redirect_url) : $app->url,
            'logo' => empty($app->logo_url) ? null : route('apps.logo', ['app_id' => $app->id]),
            'description' => empty($app->description) ? null : $app->description,
            'user_public_key' => $userRequest->userPublicKey->web,
        ]);
    }
    
    public function acceptAppConnectRequestAction(
        Request                     $request,
        AppUserRequestSessionValue  $userRequest,
        AppUserRequestActionHandler $actionHandler
    ): JsonResponse
    {
        ['passkey' => $passkey] = $request->validate([
            'passkey' => 'required|string',
        ]);
        
        return response()->json([
            'success' => true,
            'redirect_url' => $actionHandler->accept(
                $passkey,
                $request->user(),
                $userRequest
            )
        ]);
    }
    
    public function declineAppConnectRequestAction(
        AppUserRequestSessionValue  $userRequest,
        AppUserRequestActionHandler $actionHandler
    ): JsonResponse
    {
        return response()->json([
            'success' => true,
            'redirect_url' => $actionHandler->decline($userRequest)
        ]);
    }
    
    public function appLogoProxy(
        Request         $request,
        Client          $client,
        LoggerInterface $logger
    ): Response
    {
        $appId = (int)$request->route('app_id');
        $app = $this->appDb->findById($appId);
        if (!$app) {
            abort(404, 'App not found.');
        }
        
        if (!$app->logo_url) {
            abort(404, 'App logo not found.');
        }
        
        try {
            $response = $client->request(
                'GET',
                $app->logo_url,
                [
                    'verify' => false,
                    'follow_redirects' => true,
                ]
            );
            
            return response(
                $response->getBody(),
                $response->getStatusCode()
            )->header('Content-Type', $response->getHeaderLine('Content-Type'));
        } catch (\Throwable $e) {
            $logger->error('Failed to fetch app logo: {{error}}', [
                'error' => $e->getMessage(),
                'app_id' => $appId,
                'exception' => $e
            ]);
            abort(404, 'App logo could not be fetched.');
        }
    }
    
    protected function resolveAppOrFail(Request $request): ExtApp
    {
        $app = $this->appDb->findByUser($request->user());
        abort_if(!$app, 403, 'The user requesting is not linked to an app.');
        return $app;
    }
    
    protected function resolveExtUserIdOrFail(Request $request): string
    {
        $externalId = $request->route()?->parameter('ext_user_id');
        abort_if(empty($externalId), 400, 'External user ID is required.');
        return $externalId;
    }
    
    protected function resolveAppUserOrFail(ExtApp $app, string $externalId): ExtAppUser
    {
        $appUser = $this->appUserDb->findByExternalId($app, $externalId);
        abort_if(!$appUser, 404, 'App user not found.');
        return $appUser;
    }
}
