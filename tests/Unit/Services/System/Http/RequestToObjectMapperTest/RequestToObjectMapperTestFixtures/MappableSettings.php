<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures;

use App\Services\System\Http\Attributes\ValidateInput;
use App\Services\Users\Settings\Values\Theme;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Castable covering every input-mapping case: annotated scalars, enums and a
 * nullable string — plus an un-annotated property (never fillable).
 */
class MappableSettings extends AbstractCastableObject
{
    #[ValidateInput('sometimes|in:light,dark')]
    public Theme $theme = Theme::Light;

    #[ValidateInput('sometimes|integer')]
    public int $max_tokens = 4096;

    #[ValidateInput('sometimes|boolean')]
    public bool $stream = true;

    #[ValidateInput('sometimes|nullable|string|max:5')]
    public ?string $locale = null;

    /**
     * Never fillable — no #[ValidateInput] attribute.
     */
    public string $internal = 'keep-me';

    #[ValidateInput('sometimes|integer')]
    public IntStatus $status = IntStatus::Active;

    #[ValidateInput('sometimes|string')]
    public Direction $direction = Direction::North;
}
