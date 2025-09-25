<?php

namespace App\Logging;

use Monolog\Logger;

class DatabaseLogger
{
    /**
     * Create a custom Monolog instance for database logging
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger($config['name'] ?? 'database');

        $handler = new DatabaseHandler(
            level: $config['level'] ?? Logger::DEBUG
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
