<?php
declare(strict_types=1);


namespace App\Utils\Arrays;


final class RecursiveMerger
{
    private static bool $mergeNumeric;
    private static bool $strictNumericMerge;
    private static bool $allowRemoval;

    /**
     * Merges two or more arrays recursively. The later arrays will be merged into the first one.
     * Numeric keys will be merged by default, but this can be disabled with the NO_NUMERIC_MERGE option.
     * If ALLOW_REMOVAL is enabled, values set to '__UNSET' in the later arrays will be removed from the result.
     *
     * @param array $a The first array to merge into
     * @param array $b The second array to merge into the first one
     * @param array|RecursiveMergeOption ...$args Additional arrays to merge and/or options to control the merge behavior
     *
     * @return array
     */
    public static function merge(array $a, array $b, array|RecursiveMergeOption ...$args): array
    {
        self::$mergeNumeric = true;
        self::$strictNumericMerge = false;
        self::$allowRemoval = false;

        // Extract options and validate input
        $argsClean = [$b];
        foreach ($args ?? [] as $arg) {
            if ($arg instanceof RecursiveMergeOption) {
                if ($arg === RecursiveMergeOption::NO_NUMERIC_MERGE) {
                    self::$mergeNumeric = false;
                    continue;
                }

                if ($arg === RecursiveMergeOption::ALLOW_REMOVAL) {
                    self::$allowRemoval = true;
                    continue;
                }

                if ($arg === RecursiveMergeOption::STRICT_NUMERIC_MERGE) {
                    self::$strictNumericMerge = true;
                    continue;
                }

                continue;
            }

            $argsClean[] = $arg;
        }

        // Loop over all given arguments
        $currentA = $a;
        while (!empty($currentB = array_shift($argsClean))) {
            $currentA = self::mergeWalker($currentA, $currentB);
        }

        return $currentA;
    }

    /**
     * Internal helper that is used to do the traverse two arrays recursively and merge them into each other
     *
     * @param array $a The array to merge $b into
     * @param array $b The array to merge into $a
     *
     * @return array
     */
    private static function mergeWalker(
        array $a,
        array $b
    ): array
    {
        if (!self::$allowRemoval && empty($a)) {
            return $b;
        }
        if (empty($b)) {
            return $a;
        }
        foreach ($b as $k => $v) {
            if (self::$allowRemoval && $v === '__UNSET') {
                unset($a[$k]);
                continue;
            }
            if (is_numeric($k) && ((!self::$strictNumericMerge && !is_array($v)) || !self::$mergeNumeric)) {
                $a[] = $v;
                continue;
            }
            if (isset($a[$k]) && is_array($a[$k]) && is_array($v)) {
                $v = self::mergeWalker($a[$k], $v);
            }
            $a[$k] = $v;
        }

        return $a;
    }
}
