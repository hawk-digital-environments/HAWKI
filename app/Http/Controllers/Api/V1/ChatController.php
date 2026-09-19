<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\Chat\ChatService;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Formatters\Exceptions\FormatterNotFoundException;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\UnknownModelException;
use App\Services\Ai\Formatters\FormatterRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic, format-agnostic chat proxy endpoint: `POST /api/hawki/v1/chat/{format?}`.
 *
 * The controller is deliberately thin — it resolves the requested wire format
 * (default `openResponses`), delegates parsing to the formatter, execution to
 * {@see ChatService}, and rendering back to the formatter. Formatter-level request
 * errors are rendered in the formatter's own error shape so wire-format clients
 * receive spec-compliant error payloads.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly FormatterRegistry $formatters,
        private readonly ChatService $chatService,
    ) {
    }

    public function __invoke(Request $request, ?string $format = null): Response
    {
        try {
            $formatter = $this->formatters->resolve($format);
        } catch (FormatterNotFoundException) {
            $formatter = $this->formatters->resolve();
        }

        try {
            $aiRequest = $formatter->parseRequest($request);
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        }

        if ($aiRequest->wantsStreaming()) {
            try {
                return $formatter->formatStream($this->chatService->sendStreaming($aiRequest));
            } catch (ModelIdNotAvailableException $exception) {
                return $formatter->formatError(UnknownModelException::fromModelException($exception));
            }
        }

        try {
            return $formatter->formatResponse($this->chatService->send($aiRequest));
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        } catch (ModelIdNotAvailableException $exception) {
            return $formatter->formatError(UnknownModelException::fromModelException($exception));
        }
    }
}
