<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrgCreate extends Command
{
    protected $signature = 'org:create
        {name : Name of the organization to create}
        {--admins=* : Usernames to attach as organization admins}
        {--members=* : Usernames to attach as organization members}';

    protected $description = 'Create an organization and optionally attach users to it';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        // There is no unique index on the name, but duplicates would make
        // --org-by-name lookups ambiguous everywhere else.
        if (Organization::query()->where('name', $name)->exists()) {
            $this->error(sprintf('An organization named "%s" already exists.', $name));

            return self::FAILURE;
        }

        // Resolve every username before writing anything, so an unknown name
        // fails atomically instead of leaving a half-populated organization.
        $admins = $this->resolveUsers((array) $this->option('admins'));

        if (null === $admins) {
            return self::FAILURE;
        }

        $members = $this->resolveUsers((array) $this->option('members'));

        if (null === $members) {
            return self::FAILURE;
        }

        $organization = DB::transaction(static function () use ($name, $admins, $members): Organization {
            $organization = Organization::create(['name' => $name]);
            $organization->users()->attach($admins->modelKeys(), ['role' => 'admin']);
            $organization->users()->attach($members->modelKeys(), ['role' => 'member']);

            return $organization;
        });

        $this->info(sprintf(
            'Created organization "%s" (%d admin(s), %d member(s)).',
            $organization->name,
            $admins->count(),
            $members->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve usernames to users, failing with the list of unknown names.
     *
     * @param list<string> $usernames
     *
     * @return null|\Illuminate\Database\Eloquent\Collection<int, User> null when at least one username is unknown
     */
    private function resolveUsers(array $usernames): ?\Illuminate\Database\Eloquent\Collection
    {
        $users = User::withoutGlobalScopes()
            ->whereIn('username', $usernames)
            ->get()
            ->keyBy('username');

        $unknown = array_values(array_diff($usernames, $users->keys()->all()));

        if ([] !== $unknown) {
            $this->error(sprintf('Unknown username(s): %s', implode(', ', $unknown)));

            return null;
        }

        return $users->values();
    }
}
