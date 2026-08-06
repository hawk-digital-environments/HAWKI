<?php

declare(strict_types=1);

namespace App\Services\FileConverter\Interfaces;

interface FileConverterExtensionInterface extends FileConverterInterface
{
    /**
     * Returns the inner file converter.
     */
    public function getInnerConverter(): FileConverterInterface;
}
