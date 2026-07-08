<?php
/*
 * Copyright 2022 LABOR.digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2022.06.08 at 22:02
 */

declare(strict_types=1);

namespace App\Utils\Sorting\Exceptions;

/**
 * Thrown when a dependency cycle is detected while resolving topological ordering constraints.
 */
class CyclicDependencyException extends \RuntimeException
{
    /**
     * @param string[] $path The chain of items traversed when the loop was discovered.
     * @param string   $key  The item encountered again, closing the cycle.
     */
    public static function forLoopInPath(array $path, string $key): self
    {
        return new self(
            'Found a cyclic dependency in: ' . implode(' -> ', $path) . ' -> ' . $key
        );
    }
}
