<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Transient;


use Illuminate\Database\Eloquent\Model;

/**
 * This is a dummy model to hold transient data for SyncLog tracking.
 * Yes, I know this is kind of hacky, so go on, judge me :P...
 * @internal
 */
class TransientDataModel extends Model
{
    protected array $payload;
    
    /**
     * @inheritDoc
     */
    public function __construct(array $payload = [])
    {
        parent::__construct([]);
        $this->payload = $payload;
    }
    
    public function getPayload(): array
    {
        return $this->payload;
    }
}
