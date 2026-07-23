<?php
declare(strict_types=1);

return [
    /**
     * This config option is a laravel internal that is used by
     * {@see \Illuminate\Http\Middleware\TrustProxies::setTrustedProxyIpAddresses}
     * to determine which proxies to trust when determining the client's IP address and other proxy-related headers.
     */
    'proxies' => (static function () {
        $proxies = env('APP_TRUSTED_PROXIES', '');
        if (empty($proxies)) {
            return null;
        }
        $proxyList = array_map('trim', explode(',', $proxies));

        // If we find "*" or "**", we trust all proxies,
        // However, laravel can only do this, if we return "*" or "**" as a string, not as an array. So we check for this case and return the string directly.
        if (in_array('*', $proxyList, true)) {
            return '*';
        }
        if (in_array('**', $proxyList, true)) {
            return '**';
        }

        // Otherwise, we return the array of proxies.
        return $proxyList;
    })()
];
