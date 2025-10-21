<?php
declare(strict_types=1);


namespace App\Services\Auth\Util;


use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Stringable;

readonly class DisplayNameBuilder
{
    /**
     * Builds a display name based on the provided definition and value resolver.
     * The definition can be a single field name or multiple field names separated by commas.
     * If multiple fields are provided, their values will be concatenated with a space in order of their definition.
     * If multiple fields are defined, and SOME of them are empty or not set, they will be skipped.
     * However, if NONE of the fields are set and not empty, an exception will be thrown.
     * @param string $definition Either a single field name or multiple field names separated by commas: "first_name,last_name" or "display_name"
     * @param callable $valueResolver A callable that takes a field name and returns its value, if set. If the field is not set, it should return null.
     * @param LoggerInterface|null $logger An optional logger to log warnings or errors during the resolution process.
     * @return string
     */
    public static function build(
        string           $definition,
        callable         $valueResolver,
        ?LoggerInterface $logger = null
    ): string
    {
        if (!str_contains($definition, ',')) {
            return $valueResolver(trim($definition));
        }

        $values = [];
        foreach (Str::of($definition)->explode(',')->map('trim')->filter()->all() as $field) {
            try {
                $value = $valueResolver($field);
                if ($value instanceof Stringable) {
                    $value = (string)$value;
                }
                if (is_string($value) && !empty($value)) {
                    $values[] = $value;
                } else {
                    $logger?->debug(sprintf(
                        'Field in %s to be used in display name is not set',
                        $field
                    ), ['field' => $field]);
                }
            } catch (\Throwable $e) {
                $logger?->error(sprintf(
                    'Field in %s failed to be resolved for display name!',
                    $field
                ), ['field' => $field, 'exception' => $e]);
            }
        }

        if (empty($values)) {
            throw new \RuntimeException("None of the fields in $definition are set and not empty, so the display name could not be built!", 500);
        }

        return implode(' ', $values);
    }
}
