<?php

declare(strict_types=1);

namespace App\Services\Ai\Models\Access\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ModelAccessException extends HttpException
{
    public const ERROR_CODES = ['MODEL_ACCESS_DENIED'];

    private function __construct(public readonly string $errorCode, int $status, string $message)
    {
        parent::__construct($status, $message);
    }

    public static function denied(): self
    {
        return new self('MODEL_ACCESS_DENIED', 403, 'Your access to the selected model has changed.');
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->getStatusCode());
    }
}
