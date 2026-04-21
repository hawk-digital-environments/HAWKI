<?php

namespace App\Utils\Arrays;

/**
 * @api
 */
enum RecursiveMergeOption
{
    /**
     * Disables the merging of numeric keys. By default, numeric keys will be merged into each other,
     * so: [["foo"]] + [["bar"]] becomes [["bar"]]. This however is only the case for ARRAYS!
     * All other values will be appended to $a, so ["a"] + ["b"] becomes ["a", "b"].
     */
    case NO_NUMERIC_MERGE;
    /**
     * Enables the value "__UNSET" feature, which can be used in
     * the merged array in order to unset array keys in the original array.
     */
    case ALLOW_REMOVAL;
    /**
     * By default, only arrays with numeric keys are merged
     * into each other. By setting this flag ALL values will be merged into each
     * other when they have numeric keys.
     */
    case STRICT_NUMERIC_MERGE;
}
