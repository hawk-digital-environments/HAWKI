<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Marker interface for the union of IR content parts.
 *
 * Each concrete part is a readonly value object discriminated by its {@see TYPE} constant.
 * Formatters dispatch on the concrete class; the interface exists so part unions can be
 * typed as arrays ({@see ContentPart[]}).
 */
interface ContentPart
{
}
