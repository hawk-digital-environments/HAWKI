<?php
declare(strict_types=1);


namespace App\Services\Auth\Util;


use Illuminate\Http\RedirectResponse;
use Psr\Log\LoggerInterface;

readonly class AuthRedirectBuilder
{
    /**
     * Builds a redirect response based on the provided base URL and query parameters.
     *
     * Example:
     * ```php
     * $redirectResponse = AuthRedirectBuilder::build(
     *    baseUrl: 'https://example.com/callback',
     *    routeQueryParams: [ 'next' => 'dashboard.home' ],
     *    queryParams: [ 'foo' => 'bar' ],
     * );
     *
     * // This will create a redirect response to:
     * // https://example.com/callback?next=https%3A%2F%2Fexample.com%2Fdashboard&foo=bar
     * ```
     *
     * @param string $baseUrl The base URL to redirect to.
     * @param array|null $routeQueryParams An associative array where keys are query parameter names and values are route names.
     *                                     The route names will be resolved to URLs and added as query parameters.
     * @param array|null $queryParams An associative array of additional query parameters to add to the URL.
     * @param LoggerInterface|null $logger
     * @return RedirectResponse|null
     */
    public static function build(
        string           $baseUrl,
        ?array           $routeQueryParams = null,
        ?array           $queryParams = null,
        ?LoggerInterface $logger = null
    ): RedirectResponse|null
    {
        if (empty($baseUrl)) {
            $logger?->debug('No base URL provided for redirect, is your configuration correct?');
            return null;
        }

        $queryParams = $queryParams ?? [];

        if (!empty($routeQueryParams)) {
            foreach ($routeQueryParams as $key => $routeName) {
                $queryParams[$key] = route($routeName);
            }
        }

        $url = $baseUrl;
        if (!empty($queryParams)) {
            $url = url()->query($baseUrl, $queryParams);
        }

        $logger?->debug('Created redirect response to url', ['url' => $url]);
        return redirect($url);
    }
}
