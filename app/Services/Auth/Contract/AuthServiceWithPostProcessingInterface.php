<?php
declare(strict_types=1);


namespace App\Services\Auth\Contract;


use App\Models\User;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An extension of the {@see AuthServiceInterface} that supports post-processing after user login.
 */
interface AuthServiceWithPostProcessingInterface
{
    /**
     * Executed after the user has been authenticated successfully and the user object
     * has been retrieved. This method can perform additional actions such as logging,
     * updating user data, or redirecting the user to a specific page.
     *
     * This method is ONLY called after successful authentication IF there is already a known user object.
     *
     * @param User $user The user that was just logged in.
     * @param Request $request The current HTTP request.
     * @return RedirectResponse|null A response if, for example, a redirection is needed, or null to continue normal flow.
     */
    public function afterLoginWithUser(User $user, Request $request): Response|null;

    /**
     * Executed after a new user has been created as part of the authentication process.
     * This method can perform additional actions such as sending welcome emails,
     * initializing user settings, or redirecting the user to a specific page.
     *
     * This method is ONLY called after successful authentication IF there is not yet a user object, meaning the user will be redirected to the "registration" flow.
     *
     * We need this separate method, because the user was authenticated by an external service, but we do not yet have a local user record for them.
     *
     * @param AuthenticatedUserInfo $userInfo The information about the newly authenticated user.
     * @param Request $request The current HTTP request.
     * @return Response|null A response if, for example, a redirection is needed, or null to continue normal flow.
     */
    public function afterLoginWithoutUser(AuthenticatedUserInfo $userInfo, Request $request): Response|null;
}
