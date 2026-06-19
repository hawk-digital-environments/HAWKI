<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

readonly class JsMigrationCreator
{
    public function __construct(private Filesystem $files)
    {
    }

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
