<?php

namespace App\Services\Auth;

use Illuminate\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;


readonly class ShibbolethService
{
    public function __construct(
        private Repository      $config,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * Authenticates the user via Shibboleth and returns user information if authenticated.
     * If the user is not authenticated, redirects to the Shibboleth login page.
     *
     * @param Request $request The HTTP request instance.
     * @return array|RedirectResponse|JsonResponse User information array if authenticated,
     *                                             redirection to the login page if not authenticated,
     *                                             or a JSON error response if required attributes are missing or the login path is not set.
     */
    public function authenticate(Request $request): array|RedirectResponse|JsonResponse
    {
        $usernameVar = $this->config->get('shibboleth.attribute_map.username', 'REMOTE_USER');

        try {
            $username = $this->getServerVarOrFail($request, $usernameVar);
            $this->logger->debug('Authenticated Shibboleth user', ['username' => $username]);
        } catch (\RuntimeException $e) {
            // Redirect to the Shibboleth login page
            $loginPath = $this->getLoginPath();

            if (!empty($loginPath)) {
                $this->logger->debug('Redirecting to Shibboleth login path', ['login_path' => $loginPath, 'exception' => $e]);
                return redirect($loginPath);
            }

            $this->logger->error('Shibboleth login path is not set in configuration', ['exception' => $e]);

            // Error handling if the login path is not set
            return response()->json(['error' => 'Login path is not set'], 500);
        }

        try {
            $userdata = [
                'username' => $username,
                'name' => $this->getDisplayName($request),
                'email' => $this->getServerVarOrFail($request, $this->config->get('shibboleth.attribute_map.email')),
                'employeetype' => $this->getServerVarOrFail($request, $this->config->get('shibboleth.attribute_map.employeetype'))
            ];

            $this->logger->debug('Retrieved Shibboleth user attributes', ['userdata' => $userdata]);

            return $userdata;
        } catch (\RuntimeException $e) {
            $this->logger->error('Failed to resolve userdata for Shibboleth auth', ['exception' => $e]);
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Either the logout URL from configuration with the return parameter appended,
     * or null if no logout URL is configured.
     * @return string|null
     */
    public function getLogoutPath(): ?string
    {
        $logoutPath = $this->config->get('shibboleth.logout_path');

        if (empty($logoutPath)) {
            $this->logger->warning('Shibboleth logout path is not set in configuration');
            return null;
        }

        // Automatically append the return parameter to the logout URL
        // This ensures that after logout, the user is redirected back to the homepage
        return url()->query($logoutPath, ['return' => url('/')]);
    }

    /**
     * Gets the Shibboleth login path from configuration and appends the target parameter.
     *
     * @return string|null
     */
    private function getLoginPath(): ?string
    {
        $loginPath = $this->config->get('shibboleth.login_path');

        if (empty($loginPath)) {
            $this->logger->warning('Shibboleth login path is not set in configuration');
            return null;
        }

        // Automatically append the target parameter to the login URL
        // This ensures that after successful authentication, the user is redirected back to the intended page
        $targetPath = Route::getRoutes()->getByName('web.auth.shibboleth.login');
        if ($targetPath) {
            $loginPath = url()->query($loginPath, ['target' => url('/' . $targetPath->uri())]);
            $this->logger->debug('Appended target parameter to Shibboleth login path', ['login_path' => $loginPath]);
        }

        return $loginPath;
    }

    /**
     * Builds the display name from a list of attributes specified in the configuration.
     * If the nameList configuration is empty, it falls back to a single attribute.
     *
     * @param Request $request
     * @return string
     */
    private function getDisplayName(Request $request): string
    {
        $nameField = $this->config->get('shibboleth.attribute_map.name');
        if (!str_contains($nameField, ',')) {
            return $this->getServerVarOrFail($request, $this->config->get('shibboleth.attribute_map.name'));
        }

        $values = [];
        foreach (Str::of($nameField)->explode(',')->map('trim')->filter()->all() as $field) {
            try {
                $values[] = $this->getServerVarOrFail($request, $field);
            } catch (\RuntimeException) {
                // Ignore missing fields
                $this->logger->debug('Field in SHIBBOLETH_NAME_VAR is not set in $_SERVER', ['field' => $field]);
            }
        }

        if (empty($values)) {
            throw new \RuntimeException("None of the fields in SHIBBOLETH_NAME_VAR are set and not empty", 500);
        }

        return implode(' ', $values);
    }

    private function getServerVarOrFail(Request $request, string $var): string
    {
        $redirectVar = 'REDIRECT_' . $var;
        $value = $request->server($var) ?? $request->server('REDIRECT_' . $var) ?? null;

        if (!empty($value)) {
            return $value;
        }

        // Try to find value case-insensitively (This is rather expensive, so we log it as a warning)
        foreach ($request->server->all() as $key => $val) {
            if (Str::lower($key) === Str::lower($var) && !empty($val)) {
                $this->logger->warning("Found server variable '$key' case-insensitively for expected '$var'");
                return $val;
            }
            if (Str::lower($key) === Str::lower($redirectVar) && !empty($val)) {
                $this->logger->warning("Found server variable '$key' case-insensitively for expected '$redirectVar'");
                return $val;
            }
        }

        throw new \RuntimeException("Neither $var nor $redirectVar are set and not empty", 400);
    }
}
