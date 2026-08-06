<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use App\Services\ExtApp\Config\ExtAppConfig;
use App\Services\System\Time\CarbonClockInterface;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Routing\UrlGenerator;

readonly class ExtAppUrlBuilder
{
    public const string REDIRECT_STATUS_PARAM = 'hawki-connect-status';
    public const string REDIRECT_STATUS_SUCCESS = 'success';
    public const string REDIRECT_STATUS_DECLINED = 'declined';

    public function __construct(
        private UrlGenerator         $urlGenerator,
        private ExtAppConfig         $config,
        private CarbonClockInterface $clock,
    )
    {
    }

    /**
     * Build redirect URL with declined status
     */
    public function redirectOnConnectionDeclined(ExtApp $app): string
    {
        return $this->attachQueryParamToUrl(
            $app->redirect_url,
            self::REDIRECT_STATUS_PARAM,
            self::REDIRECT_STATUS_DECLINED
        );
    }

    /**
     * Build redirect URL with success status
     */
    public function redirectOnConnectionAccepted(ExtApp $app): string
    {
        return $this->attachQueryParamToUrl(
            $app->redirect_url,
            self::REDIRECT_STATUS_PARAM,
            self::REDIRECT_STATUS_SUCCESS
        );
    }

    /**
     * Build a temporary signed URL for the logo proxy route of the given app.
     * The URL will be valid for the duration specified in the config for external app connect request timeout.
     * If the app does not have a logo URL, null will be returned.
     */
    public function logoProxyUrl(ExtApp $app): string|null
    {
        if ($app->logo_url === null) {
            return null;
        }

        return $this->urlGenerator->temporarySignedRoute(
            'v1.ext-apps.proxyLogo',
            // The logo proxy should at least be valid for the duration of the connect request,
            // otherwise the client might not be able to load the logo when accepting the connection request.
            $this->clock->now()->addMinutes($this->config->externalAppConnectRequestTimeout),
            ['appId' => $app->id]
        );
    }

    protected function attachQueryParamToUrl(string $url, string $key, string $value): string
    {
        $uri = new Uri($url);
        $query = $uri->getQuery();
        if ($query === '') {
            $query = $key . '=' . $value;
        } else {
            $query .= '&' . $key . '=' . $value;
        }
        return (string)$uri->withQuery($query);
    }
}
