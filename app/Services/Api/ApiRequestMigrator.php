<?php
declare(strict_types=1);


namespace App\Services\Api;


use App\Services\Api\Value\ApiRequestFieldConfig;
use App\Services\Message\MessageDb;
use App\Services\Message\ThreadIdHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

readonly class ApiRequestMigrator
{
    public const REQUEST_VERSION_2 = 'v2';
    
    public function __construct(
        private ThreadIdHelper $threadIdHelper,
        private MessageDb      $messageDb
    )
    {
    }
    
    public function migrate(
        Request                $request,
        ?ApiRequestFieldConfig $config = null
    ): Request
    {
        $config ??= new ApiRequestFieldConfig();
        $version = $request->get($config->versionField);
        if ($version === null) {
            return $request;
        }
        
        if ($version === self::REQUEST_VERSION_2) {
            return $this->migrateValidatedDataForV2($request, $config);
        }
        
        throw ValidationException::withMessages([
            $config->versionField => ["Unsupported API version: $version"]
        ]);
    }
    
    /**
     * Migrate request data from v2 to the legacy format:
     * - v2MessageIdField -> messageIdField
     * - v2ThreadIdField -> threadIdField
     * - messageIdField and threadIdField MUST NOT be set in the request
     * - if v2MessageIdAssumesInternalId is true, v2MessageIdField is treated as internal ID
     *
     * @param Request $request
     * @param ApiRequestFieldConfig $config
     * @return Request
     */
    private function migrateValidatedDataForV2(
        Request               $request,
        ApiRequestFieldConfig $config
    ): Request
    {
        $v2Data = $request->validate([
            $config->messageIdField => 'prohibited', // MUST not be set
            $config->threadIdField => 'prohibited', // MUST not be set
            $config->v2MessageIdField => 'sometimes|integer',
            $config->v2ThreadIdField => 'sometimes|integer',
        ]);
        
        // Migrate the newer database IDs to the legacy format expected by the controllers
        if (isset($v2Data[$config->v2MessageIdField])) {
            $message = $this->messageDb->findOneById((int)$v2Data[$config->v2MessageIdField]);
            if (!$message) {
                throw ValidationException::withMessages([
                    $config->v2MessageIdField => ["Message with ID {$v2Data[$config->v2MessageIdField]} not found"]
                ]);
            }
            
            $messageId = $message->message_id;
            if ($config->v2MessageIdAssumesInternalId) {
                $messageId = $message->id;
            }
            
            $request->merge([
                $config->messageIdField => $messageId,
            ]);
        }
        
        // Migrate the thread ID by looking up the parent message and converting its message ID to a thread ID
        if (!empty($v2Data[$config->v2ThreadIdField])) {
            $parentMessage = $this->messageDb->findOneById((int)$v2Data[$config->v2ThreadIdField]);
            if (!$parentMessage) {
                throw ValidationException::withMessages([
                    $config->v2ThreadIdField => ["Parent message with ID {$v2Data[$config->v2ThreadIdField]} not found"]
                ]);
            }
            $request->merge([
                $config->threadIdField => $this->threadIdHelper->convertMessageIdToThreadId($parentMessage->message_id),
            ]);
        }
        
        return $request;
    }
}
