<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ToolAccessException extends HttpException
{
    public const ERROR_CODES = ['TOOL_ACCESS_DENIED', 'TOOL_UNAVAILABLE'];

    private function __construct(public readonly string $errorCode, int $status, string $message)
    {
        parent::__construct($status, $message);
    }

    public static function denied(): self
    {
        return new self('TOOL_ACCESS_DENIED', 403, 'Your access to the selected tool has changed.');
    }

    public static function unavailable(): self
    {
        return new self('TOOL_UNAVAILABLE', 422, 'The selected tool is currently unavailable.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->getStatusCode());
    }
}
