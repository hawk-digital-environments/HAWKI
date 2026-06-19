<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

readonly class BackendMigrationCreator
{
    public function __construct(private Filesystem $files)
    {
    }

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
