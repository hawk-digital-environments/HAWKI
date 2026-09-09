<?php
declare(strict_types=1);

namespace App\Services\Auth\Value;

/**
 * How the visitor is expected to start a login, derived from the configured auth service.
 *
 * This is the discriminator the frontend switches on: it decides whether to render a
 * username/password form or a single "continue to your identity provider" button.
 */
enum AuthMode: string
{
    /**
     * The auth service takes a username and password
     * (see {@see \App\Services\Auth\Contract\AuthServiceWithCredentialsInterface}).
     * The frontend renders a form and posts it to the login action.
     */
    case CREDENTIALS = 'credentials';

    /**
     * The auth service authenticates the visitor elsewhere (OIDC, Shibboleth). The frontend
     * must navigate the browser to the accompanying start URL; there is nothing to type.
     */
    case REDIRECT = 'redirect';
}
