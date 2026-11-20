<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\System\Container\ServiceLocator;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;

/**
 * Rebinds {@see HasContextualScopesTrait}'s statics for specific models.
 *
 * The framework clears Eloquent's booted models when a test tears down its
 * application, but the trait's statics survive — a model that already booted
 * in an earlier test never registers its contextual global scopes again, so
 * it silently runs unscoped for the rest of the process. Production boots
 * one application per process and is unaffected.
 *
 * Tests that depend on a model's contextual scopes rebind them for the
 * models they need; the returned closure restores the previous statics so
 * the rest of the suite keeps its behaviour.
 */
final class ContextualScopeStatics
{
    /**
     * @param class-string ...$modelClasses
     *
     * @return \Closure restores the previous statics
     */
    public static function rebind(string ...$modelClasses): \Closure
    {
        $previous = [];

        foreach ($modelClasses as $modelClass) {
            $previous[$modelClass] = [
                'hcst_booted' => self::readStatic($modelClass, 'hcst_booted'),
                'hcst_serviceLocator' => self::readStatic($modelClass, 'hcst_serviceLocator'),
                'hcst_scopeContext' => self::readStatic($modelClass, 'hcst_scopeContext'),
            ];

            self::writeStatic($modelClass, 'hcst_booted', false);
            // Fresh instances of the current test's app — typed statics cannot
            // be nulled, and re-registration must not reuse a stale app's.
            self::writeStatic($modelClass, 'hcst_serviceLocator', app(ServiceLocator::class));
            self::writeStatic($modelClass, 'hcst_scopeContext', app(ScopeContext::class));
        }

        return static function () use ($previous): void {
            foreach ($previous as $modelClass => $statics) {
                foreach ($statics as $name => $value) {
                    self::writeStatic($modelClass, $name, $value);
                }
            }
        };
    }

    private static function readStatic(string $class, string $name): mixed
    {
        $property = new \ReflectionProperty($class, $name);
        $property->setAccessible(true);

        return $property->getValue();
    }

    private static function writeStatic(string $class, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty($class, $name);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
