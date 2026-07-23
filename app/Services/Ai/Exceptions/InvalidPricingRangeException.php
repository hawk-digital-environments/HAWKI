<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidPricingRangeException extends \LogicException implements AiExceptionInterface
{
    public static function totalRangeWithExistingRanges(): self
    {
        return new self('Cannot add a total range (0–∞) when other bounded ranges already exist.');
    }

    public static function withExistingTotalRange(): self
    {
        return new self('Cannot add a bounded range when a total range (0–∞) already exists.');
    }

    public static function overlappingWithExistingRange(): self
    {
        return new self('Cannot add a pricing range that overlaps with an existing range.');
    }
}
