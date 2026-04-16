<?php
declare(strict_types=1);


namespace App\Utils\Casts\Casters;


use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

readonly class DateCaster implements CastsValue, BuiltInCasterInterface
{
    public function __construct(
        private CastType $type,
        private ?string  $format = null
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function get(object $object, string $stored): mixed
    {
        return match ($this->type) {
            CastType::DATE => Carbon::parse($stored)->startOfDay(),
            CastType::IMMUTABLE_DATE => CarbonImmutable::parse($stored)->startOfDay(),
            CastType::DATETIME => $this->format ? Carbon::createFromFormat($this->format, $stored) : Carbon::parse($stored),
            CastType::IMMUTABLE_DATETIME => $this->format ? CarbonImmutable::createFromFormat($this->format, $stored) : CarbonImmutable::parse($stored),
            CastType::TIMESTAMP => (int)$stored,
            default => null,
        };
    }

    /**
     * @inheritDoc
     */
    public function set(object $object, mixed $value): string
    {
        if ($this->type === CastType::TIMESTAMP) {
            return (string)($value instanceof \DateTimeInterface ? $value->getTimestamp() : (int)$value);
        }

        $dt = $value instanceof \DateTimeInterface ? $value : Carbon::parse($value);

        $fmt = $this->format ?? match ($this->type) {
            CastType::DATE, CastType::IMMUTABLE_DATE => 'Y-m-d',
            default => 'Y-m-d H:i:s',
        };

        return $dt->format($fmt);
    }

    /**
     * @inheritDoc
     */
    public static function argsForAttribute(CastType|null $type, string $typeString, ?string $format): array|null
    {
        $isDateType = match ($type) {
            CastType::DATE,
            CastType::IMMUTABLE_DATE,
            CastType::DATETIME,
            CastType::IMMUTABLE_DATETIME,
            CastType::TIMESTAMP => true,
            default => false,
        };

        if (!$isDateType) {
            return null;
        }

        return [$type, $format];
    }

    /**
     * @inheritDoc
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null
    {
        $typeName = $prop->getType()?->getName() ?? '';

        // DateTimeImmutable and its subclasses (e.g. CarbonImmutable) → immutable_datetime.
        // Must be checked before DateTimeInterface because DateTimeImmutable implements it.
        if (\is_a($typeName, \DateTimeImmutable::class, true)) {
            return [CastType::IMMUTABLE_DATETIME];
        }

        // DateTime, DateTimeInterface, and their subclasses (e.g. Carbon) → datetime.
        if (\is_a($typeName, \DateTimeInterface::class, true)) {
            return [CastType::DATETIME];
        }

        return null;
    }
}
