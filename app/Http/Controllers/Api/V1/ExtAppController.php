<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EstablishExtAppConnectionRequest;
use App\Http\Requests\Api\V1\ExtAppLogoProxyRequest;
use App\Models\ExtApp;
use App\Services\ExtApp\ExtAppUserConnector;
use App\Services\ExtApp\Repositories\ExtAppRepository;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use Psr\Log\LoggerInterface;

class ExtAppController extends Controller
{
    use Actions\FetchOne;

    /**
     * Establishes a connection between an external app and a user based on the provided connect request and passkey.
     * The passkey is the encrypted passkey of the user, to transfer to the external app.
     *
     * @param EstablishExtAppConnectionRequest $request The incoming request containing the connect request and passkey.
     * @param ExtAppUserConnector $connector The service responsible for handling the connection logic.
     * @return DataResponse A response containing the connected app's information if successful.
     * @throws \Illuminate\Http\Exceptions\HttpResponseException If the connection could not be established.
     */
    #[Authorize('establish-connection', ExtApp::class)]
    public function establishConnection(
        EstablishExtAppConnectionRequest $request,
        ExtAppUserConnector              $connector
    ): DataResponse
    {
        $extAppUser = $connector->connect(
            $request->user(),
            $request->getPasskey(),
            $request->getConnectRequest()
        );

        if (!$extAppUser) {
            abort(400, 'Failed to establish connection. Did the connect request time out?');
        }

        return new DataResponse([
            $extAppUser->app
        ]);
    }

    /**
     * To avoid cors issues on the client side when fetching external app logos from their original URLs,
     * this endpoint serves as a proxy to fetch the logo and return it to the client.
     * The endpoint requires authorization to ensure that only authenticated users can access the logos of external apps.
     */
    #[Authorize('view-logo', ExtApp::class)]
    public function logoProxy(
        ExtAppLogoProxyRequest $request,
        ExtAppRepository       $extAppRepository,
        Client                 $client,
        LoggerInterface        $logger
    ): Response
    {
        $app = $extAppRepository->findOne($request->getAppId());
        if (!$app || !$app->logo_url) {
            abort(404, 'Logo not found.');
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
                'app_id' => $app->id,
                'exception' => $e
            ]);
            abort(404, 'App logo could not be fetched.');
        }
    }
}
