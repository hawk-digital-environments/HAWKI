<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\AbstractCastableObjectTestFixtures;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;
use App\Utils\Casts\Values\CastType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class CastsDateConfig extends AbstractCastableObject
{
    // Explicit annotation required: 'date' stores Y-m-d only, which differs from
    // the 'datetime' that would be inferred from the Carbon type hint.
    #[CastedValue(CastType::DATE)]
    public ?Carbon $created_at = null;

    // Explicit annotation required: same reason as above for the immutable variant.
    #[CastedValue(CastType::IMMUTABLE_DATE)]
    public ?CarbonImmutable $born_at = null;

    // No annotation needed: Carbon extends DateTime → 'datetime' is inferred automatically.
    public ?Carbon $updated_at = null;

    // No annotation needed: CarbonImmutable extends DateTimeImmutable → 'immutable_datetime' is inferred.
    public ?CarbonImmutable $finished_at = null;

    #[CastedValue('datetime:d.m.Y H:i')]
    public ?Carbon $formatted_at = null;

    #[CastedValue('immutable_datetime:d.m.Y H:i')]
    public ?CarbonImmutable $immutable_formatted_at = null;

    #[CastedValue(CastType::TIMESTAMP)]
    public int $ts = 0;
}
