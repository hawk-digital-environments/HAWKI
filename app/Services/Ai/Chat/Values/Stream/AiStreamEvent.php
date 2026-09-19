<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Marker interface for the union of IR stream events.
 *
 * Each concrete event is a readonly value object discriminated by its {@see TYPE}
 * constant. Formatters dispatch on the concrete class; the interface exists so event
 * streams can be typed ({@see AiStreamEvent[]}).
 */
interface AiStreamEvent
{
}
