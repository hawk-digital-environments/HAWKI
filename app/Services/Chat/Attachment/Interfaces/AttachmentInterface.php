<?php

namespace App\Services\Chat\Attachment\Interfaces;


interface AttachmentInterface
{
    public function store($file, string $category): array;
}
