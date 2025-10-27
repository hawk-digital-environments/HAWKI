<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthRedirectBuilder;
use App\Services\Auth\Util\DisplayNameBuilder;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Jumbojett\OpenIDConnectClient;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;

#[Singleton]
readonly class OidcService implements AuthServiceInterface, AuthServiceWithLogoutRedirectInterface
{

    public function __construct(
        #[Config('open_id_connect.oidc_idp')]
        private string          $idp,
        #[Config('open_id_connect.oidc_client_id')]
        private string          $clientId,
        #[Config('open_id_connect.oidc_client_secret')]
        #[SensitiveParameter]
        private string          $clientSecret,
        #[Config('open_id_connect.oidc_scopes')]
        private array           $scopes,
        #[Config('open_id_connect.attribute_map.username')]
        private string          $usernameAttribute,
        #[Config('open_id_connect.attribute_map.email')]
        private string          $emailAttribute,
        #[Config('open_id_connect.attribute_map.employeetype')]
        private string          $employeeTypeAttribute,
        #[Config('open_id_connect.attribute_map.name')]
        private string          $nameAttribute,
        #[Config('open_id_connect.oidc_logout_path')]
        private string          $logoutPath,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        if (empty($this->idp) || empty($this->clientId) || empty($this->clientSecret)) {
            throw new AuthFailedException('OIDC configuration variables are not set properly.', 500);
        }

        $oidc = new OpenIDConnectClient($this->idp, $this->clientId, $this->clientSecret);
        $oidc->addScope($this->scopes);

        try {
            // Attempt to authenticate the user
            if ($oidc->authenticate()) {
                $request->session()->put('oidc_id_token', $oidc->getIdToken());
                $this->logger->debug('Authenticated OIDC user');
            }
        } catch (\Throwable $e) {
            throw new AuthFailedException('OIDC authentication failed', 401, $e);
        }

        try {
            return new AuthenticatedUserInfo(
                username: $this->getUserInfoOrFail($oidc, $this->usernameAttribute),
                displayName: DisplayNameBuilder::build(
                    definition: $this->nameAttribute,
                    valueResolver: fn(string $field) => $this->getUserInfoOrFail($oidc, $field),
                    logger: $this->logger
                ),
                email: $this->getUserInfoOrFail($oidc, $this->emailAttribute),
                employeeType: $this->getUserInfoOrFail($oidc, $this->employeeTypeAttribute),
            );
        } catch (\Exception $e) {
            throw new AuthFailedException('Failed to resolve userdata for OIDC auth', 500, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getLogoutResponse(Request $request): ?RedirectResponse
    {
        $idTokenHint = $request->session()->get('oidc_id_token');

        $params = [];
        if (!empty($idTokenHint)) {
            $params = [
                'id_token_hint' => $idTokenHint
            ];
        }

        return AuthRedirectBuilder::build(
            $this->logoutPath,
            [
                'post_logout_redirect_uri' => 'login'
            ],
            $params
        );
    }

    private function getUserInfoOrFail(OpenIDConnectClient $oidc, string $var): string
    {
        $value = $oidc->requestUserInfo($var);
        if (empty($value)) {
            throw new \RuntimeException("OIDC: User info attribute '{$var}' is missing or empty.");
        }
        if (!is_string($value)) {
            throw new \RuntimeException("OIDC: User info attribute '{$var}' is not a string.");
        }
        return $value;
    }
}
