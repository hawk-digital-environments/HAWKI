<?php
declare(strict_types=1);


namespace App\Http\Errors;


use LaravelJsonApi\Core\Document\Error;
use LaravelJsonApi\Core\Exceptions\JsonApiException;

/**
 * Throws JSON:API errors that lead with a machine-readable `code`.
 *
 * Clients must be able to branch on an error without parsing prose: the SPA has to tell
 * "the policy changed while you were reading it" apart from "you are already registered", and
 * both arrive as a 409. The `code` is therefore the primary payload — `title` and `detail` are
 * for humans reading logs, and neither may be relied upon by a client.
 *
 * Codes are stable API surface. Renaming one is a breaking change.
 */
final class CodedError
{
    /**
     * @param string      $code   Stable, snake_case error code the client branches on.
     * @param int         $status HTTP status code.
     * @param string      $title  Short, human-readable summary.
     * @param string|null $detail Optional longer explanation. Never include secrets.
     */
    public static function make(string $code, int $status, string $title, ?string $detail = null): JsonApiException
    {
        return JsonApiException::error(
            Error::make()
                ->setCode($code)
                ->setStatus((string)$status)
                ->setTitle($title)
                ->setDetail($detail)
        );
    }

    /**
     * Same as {@see make()}, but throws right away so call sites read as a guard clause.
     */
    public static function abort(string $code, int $status, string $title, ?string $detail = null): never
    {
        throw self::make($code, $status, $title, $detail);
    }
}
