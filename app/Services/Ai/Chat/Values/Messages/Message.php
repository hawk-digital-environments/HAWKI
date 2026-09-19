<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

/**
 * Marker interface for the union of IR messages.
 *
 * Each concrete message is a readonly value object discriminated by its {@see ROLE}
 * constant and carrying an array of role-constrained content parts.
 */
interface Message
{
}
