<?php

namespace App\Services\AI\Interfaces;

interface FormatterInterface
{
    public function format(array $payload): array;


    public function formatMessage(array $message, array $attachmentsMap, string $modelId): array;


}
