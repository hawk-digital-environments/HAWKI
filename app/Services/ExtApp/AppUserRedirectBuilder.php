<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtApp;
use GuzzleHttp\Psr7\Uri;

readonly class AppUserRedirectBuilder
{
    public const REDIRECT_STATUS_PARAM = 'hawki-connect-status';
    public const REDIRECT_STATUS_SUCCESS = 'success';
    public const REDIRECT_STATUS_DECLINED = 'declined';
    
    /**
     * Build redirect URL with declined status
     */
    public function decline(ExtApp $app): string
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
    public function accept(ExtApp $app): string
    {
        return $this->attachQueryParamToUrl(
            $app->redirect_url,
            self::REDIRECT_STATUS_PARAM,
            self::REDIRECT_STATUS_SUCCESS
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
