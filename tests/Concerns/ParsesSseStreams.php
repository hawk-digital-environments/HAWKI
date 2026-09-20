<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Parses an SSE stream body into `[{event, data}]` pairs, shared by the chat endpoint
 * tests and the Open Responses compliance suite so the parsing logic exists once.
 */
trait ParsesSseStreams
{
    /**
     * @return array<int, array{event: string, data: null|array<string, mixed>|string}>
     */
    private function parseSseEvents(string $body): array
    {
        $events = [];
        $currentEvent = null;

        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $currentEvent = ['event' => mb_substr($line, 7), 'data' => null];
            } elseif (str_starts_with($line, 'data: ') && null !== $currentEvent) {
                $decoded = json_decode(mb_substr($line, 6), true);
                $currentEvent['data'] = \is_array($decoded) ? $decoded : mb_substr($line, 6);
            } elseif ('' === $line && null !== $currentEvent) {
                $events[] = $currentEvent;
                $currentEvent = null;
            }
        }

        if (null !== $currentEvent) {
            $events[] = $currentEvent;
        }

        return $events;
    }

    /**
     * Parses a bare-`data:` SSE stream (no `event:` lines, e.g. the OpenAI Chat
     * Completions chunk format) into decoded frames with a `[DONE]` marker.
     *
     * @return array<int, array{data: null|array<string, mixed>|string, done: bool}>
     */
    private function parseDataFrames(string $body): array
    {
        $frames = [];

        foreach (explode("\n", $body) as $line) {
            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = mb_substr($line, 6);

            if ('[DONE]' === $payload) {
                $frames[] = ['data' => '[DONE]', 'done' => true];

                continue;
            }

            $decoded = json_decode($payload, true);
            $frames[] = ['data' => \is_array($decoded) ? $decoded : $payload, 'done' => false];
        }

        return $frames;
    }

    /**
     * @param array<int, array{event: string, data: mixed}> $events
     */
    private function firstEvent(array $events, string $name): array
    {
        foreach ($events as $event) {
            if ($event['event'] === $name) {
                return $event;
            }
        }

        self::fail("Expected at least one '{$name}' event.");
    }

    /**
     * Captures the streamed body of a TestResponse whose content is sent lazily
     * (StreamedResponse), returning the response and the captured output.
     *
     * @param \Illuminate\Testing\TestResponse $response
     *
     * @return array{0: object, 1: string}
     */
    private function captureStreamedBody(object $response): array
    {
        $captured = '';
        ob_start(static function (string $buffer) use (&$captured): string {
            $captured .= $buffer;

            return '';
        });
        $response->baseResponse->sendContent();
        ob_end_clean();

        return [$response, $captured];
    }
}
