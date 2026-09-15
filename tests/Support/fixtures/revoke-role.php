<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\RoleAssignmentService;
use App\Services\Admin\RoleGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::withoutGlobalScopes()->findOrFail((int) $argv[1]);
echo "ready\n";

try {
    app(RoleGuard::class)->mutate(static function () use ($user, $argv): void {
        echo "locked\n";

        if ('hold' === ($argv[2] ?? null)) {
            fgets(\STDIN);
        }

        app(RoleAssignmentService::class)->replace($user, []);
    });

    exit(0);
} catch (ValidationException) {
    exit(2);
}
