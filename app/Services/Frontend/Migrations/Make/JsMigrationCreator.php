<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Writes the TypeScript migration stub for a new frontend migration.
 *
 * The stub is placed alongside the PHP migration in the run-type subfolder under
 * `resources/js/migrations/`. The frontend runner discovers it by filename and
 * executes the exported `migrate()` function in the user's browser.
 */
readonly class JsMigrationCreator
{
    public function __construct(private Filesystem $files)
    {
    }

    /**
     * Copies the JS stub to `{$path}/{$name}.ts`, creating parent directories as needed.
     *
     * @param string $name  Full migration filename without the `.ts` extension.
     * @param string $path  Absolute directory path where the file will be written.
     * @return string       Absolute path of the created file.
     */
    public function create(string $name, string $path): string
    {
        $stub = Path::join(__DIR__, 'stubs', 'js_migration.stub');
        $path = Path::join($path, $name . '.ts');
        $this->files->ensureDirectoryExists(dirname($path));
        $stubContent = $this->files->get($stub);
        $this->files->put($path, $stubContent);

        return $path;
    }
}
