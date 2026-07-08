<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use App\Services\ExternalContent\Exceptions\FailedToFetchUrlException;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

#[Singleton]
readonly class ProxyClient
{
    public function __construct(
        private PendingRequest $http
    )
    {
    }

    public function fetchOrThrow(string $url, int $timeout = 5): Response
    {
        $response = $this->http
            ->timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; HAWKI Link Preview Bot/1.0)',
            ])
            ->getSsrfSafe($url);

        if (!$response->successful()) {
            throw FailedToFetchUrlException::forUrl($url);
        }

        return $response;
    }
}
