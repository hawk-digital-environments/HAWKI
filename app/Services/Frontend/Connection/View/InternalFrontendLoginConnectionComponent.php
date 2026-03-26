<?php

namespace App\Services\Frontend\Connection\View;

use App\Services\Frontend\Connection\ConnectionFactory;
use Illuminate\View\Component;

class InternalFrontendLoginConnectionComponent extends Component
{
    public function __construct(
        private ConnectionFactory $connectionFactory
    )
    {
    }

    public function render(): string
    {
        $connectionJson = json_encode($this->connectionFactory->createInternalLoginConnection(), JSON_THROW_ON_ERROR);
        return <<<HTML
<script type="application/json" id="frontend-connection">
    $connectionJson
</script>
HTML;
    }
}
