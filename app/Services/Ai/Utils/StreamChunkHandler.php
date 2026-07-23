<?php
declare(strict_types=1);


namespace App\Services\Ai\Utils;


/**
 * Parses raw SSE (Server-Sent Events) stream data and invokes a callback for each
 * complete JSON payload.
 *
 * Two wire formats are supported:
 *
 * - **Standard SSE** (`data: {...}\n`) — already in the expected format; each
 *   `data: ` segment is extracted and forwarded directly.
 * - **Google/Gemini streaming** — returns a raw JSON array spread across multiple
 *   chunks without SSE framing. The handler buffers incoming bytes and extracts
 *   complete JSON objects using brace-counting, then re-frames them as `data: {...}`
 *   entries before forwarding.
 *
 * The `$onChunk` closure receives each raw JSON string (without the `data: ` prefix)
 * and is responsible for decoding and acting on it. Processing stops immediately when
 * the HTTP connection is aborted.
 */
class StreamChunkHandler
{
    private string $jsonBuffer = '';

    public function __construct(
        private readonly \Closure $onChunk
    )
    {
    }

    /**
     * Processes one raw data chunk from the stream.
     *
     * Chunks may be partial (the Google format accumulates bytes across calls until a
     * complete JSON object can be extracted). Valid, complete JSON segments trigger the
     * `$onChunk` callback; empty or non-JSON segments are silently skipped.
     */
    public function handle(string $data): void
    {
        if (!str_starts_with(trim($data), 'data: ')) {
            $data = $this->normalizeDataChunk($data);
        }

        foreach (explode("data: ", $data) as $chunk) {
            if (connection_aborted()) {
                break;
            }

            if (empty($chunk) || !json_validate($chunk)) {
                continue;
            }

            ($this->onChunk)($chunk);
        }
    }

    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        $this->jsonBuffer .= $data;

        if (trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }

        $output = "";
        while ($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $this->jsonBuffer = $extracted['rest'];
            $output .= "data: " . $jsonStr . "\n";
        }
        return $output;
    }

    private function extractJsonObject(string $buffer): ?array
    {
        $openBraces = 0;
        $startFound = false;
        $startPos = 0;

        $bufferLength = strlen($buffer);
        for ($i = 0; $i < $bufferLength; $i++) {
            $char = $buffer[$i];
            if ($char === '{') {
                if (!$startFound) {
                    $startFound = true;
                    $startPos = $i;
                }
                $openBraces++;
            } elseif ($char === '}') {
                $openBraces--;
                if ($openBraces === 0 && $startFound) {
                    $jsonStr = substr($buffer, $startPos, $i - $startPos + 1);
                    $rest = substr($buffer, $i + 1);
                    return ['jsonStr' => $jsonStr, 'rest' => $rest];
                }
            }
        }
        return null;
    }


}
