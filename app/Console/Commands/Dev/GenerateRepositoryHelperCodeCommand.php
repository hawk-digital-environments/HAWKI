<?php
declare(strict_types=1);

namespace App\Console\Commands\Dev;

use App\Services\System\Container\ServiceLocatorTrait;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

class GenerateRepositoryHelperCodeCommand extends Command
{
    use ServiceLocatorTrait;

    protected $signature = 'dev:helper:repository';

    protected $description = 'Generates helper code for repository classes to improve IDE support.';

    private bool|null $hasLaravelIdeaStubs = null;

    public function handle(): void
    {
        $this->info('Generating repository helper code...');
        $this->removeOldStubs();
        foreach ($this->findAllRepositoryClasses() as $class) {
            $this->generateStubForRepositoryClass($class);
            $this->info("Generated helper for $class");
        }
    }

    private function resolveSourceDirectories(): iterable
    {
        return [
            app_path('Services')
            // @todo when we start implementing repositories outside of app/Services, we need to add those directories here
        ];
    }

    private function getVendorPath(): string
    {
        return base_path('vendor');
    }

    private function getLaravelIdeaStubsPath(): string
    {
        return Path::join($this->getVendorPath(), '_laravel_idea');
    }

    private function getOwnStubPath(): string
    {
        return Path::join($this->getVendorPath(), '_hawki_ide_helpers');
    }

    private function hasLaravelIdeaStubs(): bool
    {
        if ($this->hasLaravelIdeaStubs === null) {
            $this->hasLaravelIdeaStubs = is_dir($this->getLaravelIdeaStubsPath());
        }
        return $this->hasLaravelIdeaStubs;
    }

    private function removeAllStubFilesInDir(string $dir): void
    {
        $filePrefix = '_hawki_ide_helper_repository_';
        foreach (glob(Path::join($dir, "$filePrefix*.php"), GLOB_NOSORT) as $file) {
            unlink($file);
        }
    }

    private function removeOldStubs(): void
    {
        if ($this->hasLaravelIdeaStubs()) {
            $this->removeAllStubFilesInDir($this->getLaravelIdeaStubsPath());
        }
        $this->removeAllStubFilesInDir($this->getOwnStubPath());
    }

    private function findAllRepositoryClasses(): iterable
    {
        foreach ($this->resolveSourceDirectories() as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && str_ends_with($file->getFilename(), 'Repository.php')) {
                    if ($this->output->getverbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->line("Including file: " . $file->getPathname());
                    }
                    require_once $file->getPathname();
                }
            }
        }

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(AbstractRepository::class)) {
                continue;
            }
            yield $class;
        }
    }

    private function generateStubForRepositoryClass(string $repositoryClass): void
    {
        $baseRepositoryClass = null;
        foreach (class_parents($repositoryClass) as $parent) {
            $docComment = (new \ReflectionClass($parent))->getDocComment();
            if ($docComment && str_contains($docComment, '@template')) {
                $baseRepositoryClass = $parent;
                break;
            }
        }

        if ($baseRepositoryClass === null) {
            return;
        }

        $extendedRepositoryClass = get_parent_class($repositoryClass);
        if (!$extendedRepositoryClass || !is_a($extendedRepositoryClass, AbstractRepository::class, true)) {
            return;
        }

        /** @var AbstractRepository $repository */
        $repository = $this->getService($repositoryClass);
        $modelClass = $repository->getModelClass();

        $reflection = new \ReflectionClass($repositoryClass);
        $templateParam = $this->extractTemplateParamName($baseRepositoryClass);
        $methodStubs = $this->collectMethodStubs($reflection, $modelClass, $templateParam);

        $classBody = $methodStubs ? implode("\n\n", $methodStubs) . "\n" : '';
        $content = sprintf(
            "namespace %s {\n    /**\n     * @extends \\%s<\\%s>\n     */\n    class %s extends \\%s\n    {\n%s    }\n}",
            $reflection->getNamespaceName(),
            $baseRepositoryClass,
            $modelClass,
            $reflection->getShortName(),
            $extendedRepositoryClass,
            $classBody
        );

        if ($this->hasLaravelIdeaStubs()) {
            $dir = $this->getLaravelIdeaStubsPath();
        } else {
            $this->prepareOwnStubDirectory();
            $dir = $this->getOwnStubPath();
        }

        file_put_contents(
            Path::join($dir, '_hawki_ide_helper_repository_' . $reflection->getShortName() . '.php'),
            "<?php\n/** @noinspection all */\n\n" . $content,
            LOCK_EX
        );
    }

    private function extractTemplateParamName(string $abstractClass): string
    {
        $docComment = (new \ReflectionClass($abstractClass))->getDocComment() ?: '';
        if (preg_match('/@template\s+(\w+)\s+of\s+/', $docComment, $matches)) {
            return $matches[1];
        }
        return 'TModel';
    }

    /**
     * @return list<string>
     */
    private function collectMethodStubs(\ReflectionClass $concreteReflection, string $modelClass, string $templateParam): array
    {
        // Methods declared directly in the concrete class already have concrete types — skip them
        $concreteMethods = [];
        foreach ($concreteReflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $concreteReflection->getName()) {
                $concreteMethods[$method->getName()] = true;
            }
        }

        $stubs = [];
        $seen = [];

        // Walk the abstract parent chain and collect methods that reference the template param
        $parentClass = $concreteReflection->getParentClass() ?: null;
        while ($parentClass && $parentClass->isAbstract() && is_a($parentClass->getName(), AbstractRepository::class, true)) {
            foreach ($parentClass->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if ($method->getDeclaringClass()->getName() !== $parentClass->getName()) {
                    continue;
                }

                $methodName = $method->getName();

                if (isset($seen[$methodName]) || isset($concreteMethods[$methodName])) {
                    $seen[$methodName] = true;
                    continue;
                }

                $seen[$methodName] = true;

                $docComment = $method->getDocComment() ?: '';
                if (!str_contains($docComment, $templateParam)) {
                    continue;
                }

                $stubs[] = $this->buildMethodStub($method, $docComment, $templateParam, $modelClass);
            }

            $parentClass = $parentClass->getParentClass() ?: null;
        }

        return $stubs;
    }

    private function buildMethodStub(
        \ReflectionMethod $method,
        string            $docComment,
        string            $templateParam,
        string            $modelClass
    ): string
    {
        $annotatedReturn = $this->resolveAnnotatedReturnType($method, $docComment, $templateParam, $modelClass);
        $visibility = $method->isPublic() ? 'public' : 'protected';
        $static = $method->isStatic() ? ' static' : '';
        $params = implode(', ', array_map($this->serializeParam(...), $method->getParameters()));
        $returnType = $method->hasReturnType() ? ': ' . $this->serializeReflectionType($method->getReturnType()) : '';

        $lines = [];
        if ($annotatedReturn !== null) {
            $lines[] = "        /** @return $annotatedReturn */";
        }
        $lines[] = "        {$visibility}{$static} function {$method->getName()}({$params}){$returnType} {}";

        return implode("\n", $lines);
    }

    /**
     * Produces a resolved @return annotation by substituting the template param with the concrete
     * model FQN and qualifying the outer type's short name via the PHP reflection return type.
     *
     * Example: "@return Collection<int, TModel>" + PHP type \Illuminate\Support\Collection + TModel=\App\Models\User
     *       => "\Illuminate\Support\Collection<int, \App\Models\User>"
     */
    private function resolveAnnotatedReturnType(
        \ReflectionMethod $method,
        string            $docComment,
        string            $templateParam,
        string            $modelClass
    ): ?string
    {
        if (!preg_match('/@return\s+([^\r\n]+?)(?:\s+\*\/|\s*$)/m', $docComment, $matches)) {
            return null;
        }

        $docReturn = $matches[1];

        if (!str_contains($docReturn, $templateParam)) {
            return null;
        }

        // Substitute template param with concrete model FQN
        $resolved = str_replace($templateParam, '\\' . $modelClass, $docReturn);

        // Qualify the outer short class name using the PHP reflection return type
        $phpReturn = $method->hasReturnType() ? $method->getReturnType() : null;
        if ($phpReturn instanceof \ReflectionNamedType && !$phpReturn->isBuiltin()) {
            $fqn = '\\' . $phpReturn->getName();
            $shortName = substr($fqn, strrpos($fqn, '\\') + 1);
            // Replace only unqualified occurrences (not already prefixed with \)
            $resolved = preg_replace(
                '/(?<!\\\\)\b' . preg_quote($shortName, '/') . '\b/',
                $fqn,
                $resolved
            );
        }

        return $resolved;
    }

    private function serializeParam(\ReflectionParameter $param): string
    {
        $str = '';

        if ($param->hasType()) {
            $str .= $this->serializeReflectionType($param->getType()) . ' ';
        }

        if ($param->isVariadic()) {
            $str .= '...';
        }

        $str .= '$' . $param->getName();

        if ($param->isDefaultValueAvailable()) {
            $default = $param->isDefaultValueConstant()
                ? $param->getDefaultValueConstantName()
                : var_export($param->getDefaultValue(), true);
            $str .= ' = ' . $default;
        }

        return $str;
    }

    private function serializeReflectionType(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(
                fn(\ReflectionType $t) => $t instanceof \ReflectionIntersectionType
                    ? '(' . $this->serializeReflectionType($t) . ')'
                    : $this->serializeReflectionType($t),
                $type->getTypes()
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map($this->serializeReflectionType(...), $type->getTypes()));
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();
        $prefix = $type->isBuiltin() ? '' : '\\';
        if ($type->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            return '?' . $prefix . $name;
        }
        return $prefix . $name;
    }

    private function prepareOwnStubDirectory(): void
    {
        $directory = $this->getOwnStubPath();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
        $gitignore = Path::join($directory, '.gitignore');
        if (!is_file($gitignore)) {
            file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }
}
