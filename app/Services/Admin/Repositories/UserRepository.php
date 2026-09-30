<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\AdministrationAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRepository extends ResourceRepository
{
    public const RESOURCE = 'users';

    public function save(?int $id, array $values, User $actor): int
    {
        return DB::transaction(function () use ($id, $values, $actor) {
            app(AdministrationAccess::class)->authorize($actor);
            User::withoutGlobalScopes()->where('employeetype', 'admin')->orderBy('id')->lockForUpdate()->get();
            if (null === $id) {
                app(AdministrationAccess::class)->authorize($actor);
                $data = Validator::make($values, [
                    'name' => 'required|string|max:255',
                    'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
                    'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                    'employeetype' => 'required|string|max:255',
                    'password' => 'required|string|min:12|max:255|confirmed',
                    'password_confirmation' => 'required|string|max:255',
                    'admin_disabled' => 'sometimes|boolean',
                ])->validate();
                $password = $data['password'];
                unset($data['password'], $data['password_confirmation']);
                $user = new User();
                $user->forceFill($data + [
                    'local_password' => Hash::make($password),
                    'publicKey' => '',
                    'avatar_id' => null,
                    'isRemoved' => false,
                    'registration_fingerprint' => null,
                ])->save();

                return (int) $user->id;
            }

            $user = User::withoutGlobalScopes()->findOrFail($id);
            abort_if((int) $user->id === 1, 403);
            $data = Validator::make($values, [
                'name' => 'sometimes|required|string|max:255',
                'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id)],
                'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'employeetype' => 'sometimes|required|string|max:255',
                'admin_disabled' => 'sometimes|boolean',
                'password' => 'sometimes|nullable|string|min:12|max:255|confirmed',
                'password_confirmation' => 'required_with:password|nullable|string|max:255',
            ])->validate();

            if (isset($data['username']) && $data['username'] !== $user->username) {
                throw ValidationException::withMessages(['username' => __('admin.errors.immutable')]);
            }

            $identity = array_intersect_key($data, array_flip(['name', 'email', 'employeetype']));

            if (array_intersect_key($data, array_flip(['name', 'username', 'email', 'employeetype', 'password', 'password_confirmation']))) {
                app(AdministrationAccess::class)->authorize($actor);
            }

            if ($identity && !filled($user->local_password)) {
                throw ValidationException::withMessages(['name' => __('admin.errors.external_identity')]);
            }

            if (filled($data['password'] ?? null) && !filled($user->local_password)) {
                throw ValidationException::withMessages(['password' => __('admin.errors.external_identity')]);
            }

            if (($identity['employeetype'] ?? $user->employeetype) !== 'admin' && $user->id === $actor->id) {
                throw ValidationException::withMessages(['employeetype' => __('admin.errors.self_disable')]);
            }

            if ($identity) {
                $user->forceFill($identity)->save();
            }

            if (filled($data['password'] ?? null)) {
                $user->forceFill(['local_password' => Hash::make($data['password'])])->save();
            }

            if (\array_key_exists('admin_disabled', $data)) {
                app(AdministrationAccess::class)->authorize($actor);

                if ($user->id === $actor->id && $data['admin_disabled']) {
                    throw ValidationException::withMessages(['admin_disabled' => __('admin.errors.self_disable')]);
                }

                $user->forceFill(['admin_disabled' => $data['admin_disabled']])->save();

                if ($data['admin_disabled']) {
                    $user->tokens()->delete();
                }
            }

            return (int) $id;
        });
    }

    public function revokeTokens(User $actor, ?string $id): array
    {
        app(AdministrationAccess::class)->authorize($actor);
        $user = User::withoutGlobalScopes()->findOrFail($id);

        return ['revoked' => $user->tokens()->delete()];
    }

    public function tokens(User $actor, ?string $id): array
    {
        app(AdministrationAccess::class)->authorize($actor);

        return ['tokens' => User::withoutGlobalScopes()->findOrFail($id)->tokens()->get(['id', 'name', 'created_at', 'last_used_at', 'expires_at'])->toArray()];
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['table' => 'users', 'delete' => false, 'columns' => ['name', 'username', 'email', 'employeetype', 'admin_disabled', 'last_login_at'], 'fields' => [
            $fields->text('name', true), $fields->field('username', 'text', 'required|string|max:255') + ['immutable' => true],
            $fields->field('email', 'text', 'required|email:rfc|max:255'), $fields->text('employeetype', true),
            $fields->field('password', 'secret', 'nullable|string|min:12|max:255'),
            $fields->field('password_confirmation', 'secret', 'nullable|string|max:255'),
            $fields->boolean('admin_disabled'),
        ]];
    }

    protected function rowAttributes(array $row): array
    {
        $result = [];

        if ((int) $row['id'] === 1) {
            $result['is_system'] = true;
        }

        $result['local_account'] = filled($row['local_password'] ?? null);

        return $result;
    }
}
