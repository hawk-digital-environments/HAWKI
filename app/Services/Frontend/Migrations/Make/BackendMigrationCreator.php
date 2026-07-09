<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Writes the PHP (Laravel migration) stub for a new frontend migration.
 *
 * The stub includes a pre-wired call to `FrontendMigrator::register()` and a `down()`
 * that always throws `NoDownForFrontendMigrationsExceptionException`, making the
 * "no rollback" constraint explicit from the moment the file is created.
 */
readonly class BackendMigrationCreator
{
    public function __construct(private Filesystem $files)
    {
    }

    /**
     * Copies the backend stub to `{$path}/{$name}.php`, creating parent directories as needed.
     *
     * @param string $name  Full migration filename without the `.php` extension.
     * @param string $path  Absolute directory path where the file will be written.
     * @return string       Absolute path of the created file.
     */
    public function create(string $name, string $path): string
    {
        $stub = Path::join(__DIR__, 'stubs', 'backend_migration.stub');
        $path = Path::join($path, $name . '.php');
        $this->files->ensureDirectoryExists(dirname($path));
        $stubContent = $this->files->get($stub);
        $this->files->put($path, $stubContent);

        return $path;
    }
}
