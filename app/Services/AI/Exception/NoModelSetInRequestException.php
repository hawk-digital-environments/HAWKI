<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


use App\Services\AI\Value\AiRequest;

class NoModelSetInRequestException extends \InvalidArgumentException implements AiServiceExceptionInterface
{
    public function __construct()
    {
        parent::__construct(sprintf(
            'No model was set in the AI request. Please set a model using %s->withModel() or pass it to the constructor',
            AiRequest::class
        ));
    }
    
}
