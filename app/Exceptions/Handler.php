<?php

// app/Exceptions/Handler.php
namespace App\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if($e instanceof MissingAttributeException){
            return response()->json([
                'success' => false,
                'error' => 'Missing required attributes'
            ], 400);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error' => 'Resource not found'
            ], 404);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied'
            ], 403);
        }

        if ($e instanceof Exception) {
            return response()->json([
                'success' => false,
                'error' => 'Process Failed'
            ], 500);
        }

        // Fallback for all other exceptions, only for API/JSON requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Server error'
            ], 500);
        }

        return parent::render($request, $e);
    }
}
