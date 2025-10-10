<?php

namespace App\Services\Frontend\Connection\View;

use App\Models\User;
use App\Services\Frontend\Connection\ConnectionFactory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\View\Component;

class InternalFrontendConnectionComponent extends Component
{
    public function __construct(
        private ConnectionFactory $connectionFactory,
        #[CurrentUser]
        private User              $user
    )
    {
    }
    
    public function render(): string
    {
        $connectionJson = json_encode($this->connectionFactory->createInternalConnection($this->user), JSON_THROW_ON_ERROR);
        return <<<HTML
<script type="application/json" id="frontend-connection">
    $connectionJson
</script>
HTML;
    }
}
