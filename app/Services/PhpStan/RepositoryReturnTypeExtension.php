<?php
declare(strict_types=1);

namespace App\Services\PhpStan;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

/**
 * Teaches PHPStan the concrete model type of a repository without forcing every
 * concrete repository to carry an "@extends AbstractRepository<TheModel>" annotation.
 *
 * Resolution is fully conventional and requires no per-method bookkeeping:
 *
 *  1. The concrete model is resolved by reusing the repository's own runtime
 *     {@see AbstractRepository::getModelClass()} (Larastan boots the real app during
 *     analysis). The guessing is pure reflection, so we instantiate without the
 *     constructor to avoid any dependency-injection concerns.
 *
 *  2. The return type is taken straight from the method's already-parsed PHPDoc and the
 *     "TModel" template type is swapped for the concrete model. So "Builder<TModel>"
 *     becomes "Builder<User>", "Collection<int, TModel>" becomes "Collection<int, User>",
 *     "TModel|null" becomes "User|null", and so on.
 *
 * Any method (existing or newly added) that mentions TModel in its return is handled
 * automatically. Methods that don't reference TModel are left untouched.
 */
final class RepositoryReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /** @var array<class-string, class-string|null> */
    private array $modelClassCache = [];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getClass(): string
    {
        // Applies to this class and all subclasses (incl. AbstractRepositoryWithContextualScopes).
        return AbstractRepository::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->getDeclaredReturnType($methodReflection)
                ->getReferencedTemplateTypes(TemplateTypeVariance::createInvariant()) !== [];
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall       $methodCall,
        Scope            $scope
    ): ?Type
    {
        $repositoryClass = $scope->getType($methodCall->var)->getObjectClassNames()[0] ?? null;
        if ($repositoryClass === null) {
            return null;
        }

        $modelClass = $this->resolveModelClass($repositoryClass);
        if ($modelClass === null) {
            // Fall back to the declared (TModel-bound) return type.
            return null;
        }

        $model = new ObjectType($modelClass);

        // Replace every TModel reference in the declared return type with the concrete model.
        // TModel is the only template parameter in the repository hierarchy.
        return TypeTraverser::map(
            $this->getDeclaredReturnType($methodReflection),
            static fn(Type $type, callable $traverse): Type => $type instanceof TemplateType
                ? $model
                : $traverse($type)
        );
    }

    private function getDeclaredReturnType(MethodReflection $methodReflection): Type
    {
        // The MethodReflection from the call site has already resolved TModel against the
        // receiver (to its bound Model, since concrete repos carry no @extends binding).
        // Re-fetch the method from the *declaring* class so TModel stays an intact template type.
        $declaringClass = $this->reflectionProvider->getClass($methodReflection->getDeclaringClass()->getName());
        return $declaringClass->getNativeMethod($methodReflection->getName())->getVariants()[0]->getReturnType();
    }

    /**
     * @param class-string $repositoryClass
     * @return class-string|null
     */
    private function resolveModelClass(string $repositoryClass): ?string
    {
        if (array_key_exists($repositoryClass, $this->modelClassCache)) {
            return $this->modelClassCache[$repositoryClass];
        }

        $modelClass = null;
        try {
            $reflection = $this->reflectionProvider->getClass($repositoryClass)->getNativeReflection();
            if (!$reflection->isAbstract() && $reflection->isSubclassOf(AbstractRepository::class)) {
                /** @var AbstractRepository $instance */
                $instance = $reflection->newInstanceWithoutConstructor();
                $modelClass = $instance->getModelClass();
            }
        } catch (\Throwable) {
            // Repository whose model cannot be resolved (e.g. guessing throws) -> fall back to declared type.
            $modelClass = null;
        }

        return $this->modelClassCache[$repositoryClass] = $modelClass;
    }
}
