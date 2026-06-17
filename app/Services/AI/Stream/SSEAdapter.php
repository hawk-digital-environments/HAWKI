<?php

declare(strict_types=1);

namespace App\Services\AI\Stream;

use Illuminate\Support\Str;

abstract class SSEAdapter implements StreamAdapterInterface
{
    protected function formatEvent(string $eventType, array $data): string
    {
        $payload = array_merge($data, ['type' => $eventType]);

        return "event: {$eventType}\ndata: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    protected function generateId(string $prefix = ''): string
    {
        $id = Str::uuid()->toString();

        return $prefix !== '' ? $prefix . '_' . $id : $id;
    }

    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }
}
