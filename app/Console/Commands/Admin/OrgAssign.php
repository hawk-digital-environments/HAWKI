<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class OrgAssign extends Command
{
    protected $signature = 'org:assign
        {username : Username of the user to assign}
        {--org= : Organization name or id; defaults to the user\'s only organization, or the single existing one}
        {--role=admin : Pivot role to set ("admin" or "member")}
        {--remove : Detach the user from the organization instead}';

    protected $description = 'Attach a user to an organization with a role, change the role, or remove the membership';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $user = User::withoutGlobalScopes()->where('username', $username)->first();

        if (null === $user) {
            $this->error(sprintf('Unknown username: %s', $username));

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($user);
        }

        $role = (string) $this->option('role');

        if (!\in_array($role, ['admin', 'member'], true)) {
            $this->error(sprintf('Invalid role "%s": expected "admin" or "member".', $role));

            return self::FAILURE;
        }

        $organization = $this->resolveOrganization($user);

        if (null === $organization) {
            return self::FAILURE;
        }

        // Attach or update only this membership: never sync(), which would
        // silently detach every other organization of the user.
        if ($user->organizations()->whereKey($organization->id)->exists()) {
            $user->organizations()->updateExistingPivot($organization->id, ['role' => $role]);
        } else {
            $user->organizations()->attach($organization->id, ['role' => $role]);
        }

        $this->info(sprintf(
            '%s is now %s of "%s".',
            $user->username,
            'admin' === $role ? 'an admin' : 'a member',
            $organization->name,
        ));

        return self::SUCCESS;
    }

    private function remove(User $user): int
    {
        $organization = $this->resolveOrganization($user);

        if (null === $organization) {
            return self::FAILURE;
        }

        if (!$user->organizations()->whereKey($organization->id)->exists()) {
            $this->error(sprintf('%s is not a member of "%s".', $user->username, $organization->name));

            return self::FAILURE;
        }

        $user->organizations()->detach($organization->id);
        $this->info(sprintf('%s was removed from "%s".', $user->username, $organization->name));

        return self::SUCCESS;
    }

    /**
     * Resolve the target organization: explicitly via --org (id or name), else
     * the user's only membership, else the single existing organization.
     */
    private function resolveOrganization(User $user): ?Organization
    {
        $orgOption = $this->option('org');

        if (\is_string($orgOption) && '' !== $orgOption) {
            $query = Organization::query()->where('name', $orgOption);

            // Guard the numeric comparison: whereKey('HAWKI') would be an
            // invalid integer literal on PostgreSQL.
            if (ctype_digit($orgOption)) {
                $query->orWhere('id', (int) $orgOption);
            }

            /** @var Collection<int, Organization> $organizations */
            $organizations = $query->get();

            if ($organizations->count() > 1) {
                $this->error(sprintf('Organization "%s" is ambiguous, use the id instead.', $orgOption));

                return null;
            }

            $organization = $organizations->first();

            if (null === $organization) {
                $this->error(sprintf('Unknown organization: %s', $orgOption));
            }

            return $organization;
        }

        $memberships = $user->organizations()->get();

        if (1 === $memberships->count()) {
            return $memberships->first();
        }

        $existing = Organization::query()->get();

        if (1 === $existing->count()) {
            return $existing->first();
        }

        $this->error('Cannot infer the organization: pass --org=<name|id>.');

        return null;
    }
}
