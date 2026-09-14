<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

trait AdminJsonApiRequests
{
    private function postAdminResource(string $uri, array $data = []): TestResponse
    {
        return $this->adminResourceRequest('POST', $uri, $data);
    }

    private function patchAdminResource(string $uri, array $data = []): TestResponse
    {
        return $this->adminResourceRequest('PATCH', $uri, $data);
    }

    private function deleteAdminResource(string $uri, array $data = []): TestResponse
    {
        return $this->adminResourceRequest('DELETE', $uri, $data);
    }

    private function adminResourceRequest(string $method, string $uri, array $data): TestResponse
    {
        $headers = ['Content-Type' => 'application/vnd.api+json', 'Accept' => 'application/vnd.api+json'];
        if (isset($data['version'])) {
            $headers['If-Match'] = '"' . $data['version'] . '"';
        }
        $values = $data['values'] ?? [];
        unset($data['version'], $data['values']);
        if ('DELETE' !== $method) {
            preg_match('~/api/hawki/v1/(admin-[^/]+)(?:/([^/]+))?$~', $uri, $matches);
            if (array_key_exists('type', $values)) {
                $values['kind'] = $values['type'];
                unset($values['type']);
            }
            $data['data'] = ['type' => $matches[1], 'attributes' => (object) $values];
            if ('PATCH' === $method) {
                $data['data']['id'] = rawurldecode($matches[2]);
            }
        }

        return $this->json($method, $uri, $data, $headers);
    }
}
