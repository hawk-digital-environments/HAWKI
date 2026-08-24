<?php

declare(strict_types=1);

namespace App\Services\Chat\AiConv\Repositories;

use App\Models\AiConv;
use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\UserTypes\UserContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

/**
 * @extends AbstractRepository<AiConv>
 */
class AiConvRepository extends AbstractRepository
{
    public function __construct(
        private readonly UserContext $userContext,
        private readonly AttachmentRepository $attachmentRepository,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function create(?string $name, ?string $systemPrompt): AiConv
    {
        $user = $this->userContext->getAuthenticatedUser();

        if (null === $user) {
            throw new AuthenticationException();
        }

        return $this->getQuery()->create([
            'conv_name' => $name ?: 'New Chat',
            'user_id' => $user->id,
            'slug' => Str::slug(Str::random(16)),
            'system_prompt' => $systemPrompt,
        ]);
    }

    public function delete(AiConv $conversation): void
    {
        // Database cascades do not dispatch AttachmentDeleting. Delete the
        // attachments through their repository so the stored files are removed.
        foreach ($conversation->messages()->with('attachments')->get() as $message) {
            $this->attachmentRepository->deleteForMessage($message);
        }

        $conversation->delete();
    }
}
