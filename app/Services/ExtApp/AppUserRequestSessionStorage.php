<?php
declare(strict_types=1);


namespace App\Services\ExtApp;


use App\Models\ExtAppUserRequest;
use App\Services\ExtApp\Value\AppUserRequestSessionValue;
use Illuminate\Session\SessionManager;

readonly class AppUserRequestSessionStorage
{
    protected const SESSION_KEY = 'app_user_request_temp_storage';
    
    public function __construct(
        protected SessionManager $session
    )
    {
    }
    
    public function store(ExtAppUserRequest $request): void
    {
        $this->session->put(self::SESSION_KEY, (string)AppUserRequestSessionValue::fromRequestModel($request));
    }
    
    public function get(): ?AppUserRequestSessionValue
    {
        $value = $this->session->get(self::SESSION_KEY);
        if (!$value) {
            return null;
        }
        
        return AppUserRequestSessionValue::fromString($value);
    }
    
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
