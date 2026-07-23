<?php
declare(strict_types=1);


namespace App\Http\Requests\Api\V1;


use Illuminate\Foundation\Http\FormRequest;

class ExtAppLogoProxyRequest extends FormRequest
{
    public function getAppId(): int
    {
        return (int)$this->route('appId');
    }
}
