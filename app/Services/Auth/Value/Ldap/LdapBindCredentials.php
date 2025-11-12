<?php
declare(strict_types=1);


namespace App\Services\Auth\Value\Ldap;


use App\Services\Auth\Exception\LdapException;
use App\Services\Auth\Util\LdapUtil;
use Psr\Log\LoggerInterface;

readonly class LdapBindCredentials
{
    public bool $isAnonymousBind;
    public string|null $bindDn;
    public string|null $bindPw;
    public string|null $bindPwRedacted;

    public function __construct(
        mixed            $bindDn,
        #[\SensitiveParameter]
        mixed            $bindPw,
        ?LoggerInterface $logger = null
    )
    {
        if (!is_string($bindDn) || empty(trim($bindDn))) {
            throw new LdapException('The LDAP bind DN must be a non-empty string. Use "anonymous" to indicate anonymous bind. ' . gettype($bindDn) . ' given.');
        }
        $bindDn = trim($bindDn);
        if ($bindDn === 'anonymous') {
            $this->isAnonymousBind = true;
            $this->bindDn = null;
            $this->bindPw = null;
            $this->bindPwRedacted = null;
        } else {
            $this->isAnonymousBind = false;
            $this->bindDn = $bindDn;
            if (!LdapUtil::looksLikeDn($this->bindDn)) {
                $logger?->warning("The provided LDAP bind DN '{$this->bindDn}' does not look like a valid DN.");
            }

            if (!is_string($bindPw) && $bindPw !== null) {
                throw new LdapException('The LDAP bind password must be a string. ' . gettype($bindPw) . ' given.');
            }
            $this->bindPw = empty($bindPw) ? null : $bindPw;
            if ($this->bindPw === null) {
                $this->bindPwRedacted = null;
            } else if (strlen($this->bindPw) < 2) {
                $this->bindPwRedacted = str_repeat('*', strlen($this->bindPw));
            } else {
                $firstChar = $this->bindPw[0];
                $lastChar = $this->bindPw[strlen($this->bindPw) - 1];
                $this->bindPwRedacted = $firstChar . str_repeat('*', max(0, strlen($this->bindPw) - 2)) . $lastChar;
            }
        }
    }

    public function __debugInfo(): array
    {
        return [
            'isAnonymousBind' => $this->isAnonymousBind,
            'bindDn' => $this->bindDn,
            'bindPw' => $this->bindPwRedacted,
        ];
    }
}
