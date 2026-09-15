<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[CoversNothing()]
class SpatieRoleConcurrencyTest extends TestCase
{
    public function testConcurrentRevocationsLeaveOneAdministrator(): void
    {
        if ('mysql' !== DB::getDriverName() || DB::table('model_has_roles')->exists()) {
            self::markTestSkipped('Requires an isolated MySQL test database without assigned roles.');
        }

        $users = [];
        $processes = [];
        $role = Role::create(['name' => 'concurrency-' . fake()->uuid(), 'display_name' => 'Concurrency test']);
        $role->syncPermissions(['admin.access', 'roles.manage']);

        try {
            for ($i = 0; 2 > $i; ++$i) {
                $users[] = $user = User::factory()->create();
                app(RoleAssignmentService::class)->replace($user, [$role->id]);
            }

            $input = new InputStream();
            $first = new Process([\PHP_BINARY, base_path('tests/Support/fixtures/revoke-role.php'), (string) $users[0]->id, 'hold']);
            $processes[] = $first;
            $first->setInput($input)->setTimeout(15)->start();
            self::assertTrue($first->waitUntil(static fn ($type, $output) => str_contains($output, 'locked')));

            $second = new Process([\PHP_BINARY, base_path('tests/Support/fixtures/revoke-role.php'), (string) $users[1]->id]);
            $processes[] = $second;
            $second->setTimeout(15)->start();
            self::assertTrue($second->waitUntil(static fn ($type, $output) => str_contains($output, 'ready')));
            self::assertTrue($second->isRunning());
            $input->write("continue\n");
            $input->close();

            self::assertSame(0, $first->wait(), $first->getErrorOutput());
            self::assertSame(2, $second->wait(), $second->getErrorOutput());
            self::assertFalse($users[0]->can('roles.manage'));
            self::assertTrue($users[1]->can('roles.manage'));
            self::assertSame(1, DB::table('role_user')->where('role_id', $role->id)->count());
            self::assertSame(1, DB::table('model_has_roles')->where('role_id', $role->id)->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            foreach ($users as $user) {
                $user->delete();
            }

            $role->delete();
        }
    }
}
