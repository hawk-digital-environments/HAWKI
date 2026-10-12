<?php

declare(strict_types=1);

namespace App\Console\Commands\Make;

use App\Services\System\Database\SettingsAndConfig\Make\SchemaMigrationCreator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;

/**
 * Shared machinery of the `make:config-schema-migration` and
 * `make:user-settings-migration` commands: prompts for the target class and the
 * migration type (create/update/delete), builds a sensible default migration for the
 * flavour's schema facade and writes it via {@see SchemaMigrationCreator}.
 */
abstract class AbstractMakeSchemaMigrationCommand extends Command implements PromptsForMissingInput
{
    final protected const string TYPE_CREATE = 'create';
    final protected const string TYPE_UPDATE = 'update';
    final protected const string TYPE_DELETE = 'delete';

    public function __construct(protected readonly SchemaMigrationCreator $creator)
    {
        parent::__construct();
    }

    final public function handle(): void
    {
        $class = $this->resolveClass((string) $this->argument('class'));
        $type = (string) $this->choice(
            'What kind of schema migration?',
            [
                self::TYPE_CREATE => 'create — first install: seed the class defaults',
                self::TYPE_UPDATE => 'update — add new properties or transform existing rows',
                self::TYPE_DELETE => 'delete — remove every row of the class (uninstall)',
            ],
            self::TYPE_UPDATE,
        );

        if (!\in_array($type, ['create', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException("Unknown migration type: {$type}");
        }

        $path = $this->creator->create(
            $type . '_' . static::migrationNameDomain() . '_' . Str::snake(class_basename($class)),
            $this->buildContent($class, $type),
        );

        $this->info('Migration created successfully.');
        $this->line($path);
    }

    /**
     * The flavour's schema facade, e.g. {@see \App\Services\System\Database\SettingsAndConfig\ConfigSchema}.
     */
    abstract protected static function schemaFqn(): string;

    /**
     * The flavour's blueprint class, used in the commented structural-transform example.
     */
    abstract protected static function blueprintFqn(): string;

    /**
     * The flavour's file-name domain, e.g. `'config'` or `'user_settings'`.
     */
    abstract protected static function migrationNameDomain(): string;

    /**
     * The question asked for the missing `class` argument.
     */
    abstract protected function classQuestion(): string;

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'class' => [$this->classQuestion(), 'E.g. AiConfig'],
        ];
    }

    /**
     * Resolves the given class name to its fully-qualified form. Unknown classes are
     * accepted with a warning — migrations are often written alongside a new class.
     */
    private function resolveClass(string $class): string
    {
        if (class_exists($class)) {
            return $class;
        }

        $this->warn(\sprintf(
            'The class "%s" does not exist yet — the migration will reference it by that name.',
            $class,
        ));

        return $class;
    }

    /**
     * Builds the migration content for the class and type. The imports carry the
     * fully-qualified names, the body uses the short ones.
     *
     * @param self::TYPE_CREATE|self::TYPE_DELETE|self::TYPE_UPDATE $type
     */
    private function buildContent(string $class, string $type): string
    {
        $schema = static::schemaFqn();
        $blueprint = static::blueprintFqn();
        $shortSchema = class_basename($schema);
        $shortClass = class_basename($class);
        $shortBlueprint = class_basename($blueprint);

        $upBody = match ($type) {
            self::TYPE_CREATE => [
                "{$shortSchema}::create({$shortClass}::class);",
            ],
            self::TYPE_UPDATE => [
                "{$shortSchema}::update({$shortClass}::class);",
                '',
                '// Structural transforms — values set inside the closure are upserted (overwrite).',
                '// Uncomment and adjust as needed:',
                "// {$shortSchema}::update({$shortClass}::class, static function ({$shortBlueprint} \$b): void {",
                "//     \$b->newProperty = (int) \$b->getRaw('oldProperty');",
                '// });',
                "// {$shortSchema}::dropKey({$shortClass}::class, 'oldProperty');",
            ],
            self::TYPE_DELETE => [
                "{$shortSchema}::drop({$shortClass}::class);",
            ],
        };

        $downBody = match ($type) {
            self::TYPE_CREATE => [
                "{$shortSchema}::drop({$shortClass}::class);",
            ],
            // Structural transforms cannot be reverted automatically — the old
            // values are only known at migration time.
            self::TYPE_UPDATE => [
                "throw new RuntimeException('Structural transforms cannot be reverted automatically.');",
            ],
            self::TYPE_DELETE => [
                "{$shortSchema}::create({$shortClass}::class);",
            ],
        };

        $headline = match ($type) {
            self::TYPE_CREATE => "Registers {$shortClass} with its default rows",
            self::TYPE_UPDATE => "Structural migration for {$shortClass}",
            self::TYPE_DELETE => "Removes every row of {$shortClass}",
        };

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            "use {$class};",
            "use {$schema};",
            'use Illuminate\\Database\\Migrations\\Migration;',
            '',
            '/**',
            " * {$headline}.",
            ' */',
            'return new class() extends Migration {',
            '    public function up(): void',
            '    {',
            ...$this->indent($upBody),
            '    }',
            '',
            '    public function down(): void',
            '    {',
            ...$this->indent($downBody),
            '    }',
            '};',
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * Indents a method body by one level.
     *
     * @param list<string> $body
     *
     * @return list<string>
     */
    private function indent(array $body): array
    {
        return array_map(
            static fn (string $line): string => '' === $line ? '' : '        ' . $line,
            $body,
        );
    }
}
