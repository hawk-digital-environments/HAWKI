<?php
declare(strict_types=1);


namespace App\Services\Encryption\Value;


use App\Services\Encryption\SaltType;

readonly class Salt implements \Stringable
{
    public function __construct(
        public SaltType $type,
        public string   $salt
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->salt;
    }
}
