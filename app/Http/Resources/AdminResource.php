<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Support\Arr;
use LaravelJsonApi\Core\Document\ResourceObject;

final class AdminResource extends ResourceObject
{
    public function __construct(string $section, array $row)
    {
        $attributes = Arr::except($row, ['id', 'type', '_version']);
        if (array_key_exists('type', $row)) {
            $attributes['kind'] = $row['type'];
        }

        parent::__construct(
            'admin-' . $section,
            (string) $row['id'],
            $attributes,
            meta: isset($row['_version']) ? ['version' => $row['_version']] : [],
        );
    }
}
