<?php
declare(strict_types=1);

return [
    'proxies' => (static function () {
        $proxies = env('APP_TRUSTED_PROXIES', '');
        if (empty($proxies)) {
            return null;
        }
        return array_map('trim', explode(',', $proxies));
    })()
];
