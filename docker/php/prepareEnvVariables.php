<?php
declare(strict_types=1);

const ENV_FILE = '/var/www/html/.env';
const ENV_FILE_TEMPLATE = '/var/www/html/.env.example';

if (file_exists(ENV_FILE) && is_writable(ENV_FILE)) {
    unlink(ENV_FILE);
}

// Load the template file, strop out all lines that define any of the already known environment variables
// and dump it to the new .env file
$knownEnvKeys = array_keys(getenv());
$env = file(ENV_FILE_TEMPLATE);

$env = array_filter($env, static function ($line) use ($knownEnvKeys) {
    $line = trim($line);
    if (empty($line)) {
        return false;
    }
    foreach ($knownEnvKeys as $key) {
        if (str_starts_with($line, $key)) {
            return false;
        }
    }
    return true;
});

file_put_contents(ENV_FILE, implode(PHP_EOL, $env));
