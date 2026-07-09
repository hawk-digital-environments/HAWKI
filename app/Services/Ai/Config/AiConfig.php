<?php
declare(strict_types=1);


namespace App\Services\Ai\Config;


use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class AiConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * The handle to use for messaging an AI.
     * The handle is a string stating with "@" followed by the AI's unique identifier, e.g. "@hawki".
     * This is the trigger to start a conversation with the AI in chat interfaces.
     * @var string
     */
    public readonly string $handle;

    /**
     * The display name of the HAWKI user.
     * This is used to display the name of the AI in the UI when the AI is represented as a user (e.g. in chat interfaces).
     * It can be different from the handle and can contain spaces and special characters.
     * @var string
     */
    public readonly string $hawkiUserDisplayName;

    /**
     * The username of the HAWKI user, which is used for mentions and other user-specific interactions in the UI.
     * @var string
     */
    public readonly string $hawkiUserUsername;

    /**
     * The storage identifier for the HAWKI user's avatar.
     * This is used to retrieve the avatar image for the AI when it is represented as a user in the UI.
     * @var StoredFileIdentifier|null
     */
    public readonly StoredFileIdentifier|null $hawkiUserAvatar;

    /**
     * @inheritDoc
     */
    public static function publicKey(): string
    {
        return 'ai';
    }

    /**
     * @inheritDoc
     */
    public function toPublicArray(Request $request): array|null
    {
        if ($request->user()) {
            return [
                'handle' => $this->handle,
                'hawkiUserDisplayName' => $this->hawkiUserDisplayName,
                'hawkiUserUsername' => $this->hawkiUserUsername,
                'hawkiUserAvatar' => $this->hawkiUserAvatar
            ];
        }
        return null;
    }

    public static function make(
        Repository     $repo,
        UserRepository $userRepository
    ): static
    {
        $hawkiUser = $userRepository->findHawki();
        return self::fromArray([
            'handle' => '@' . ltrim($repo->get('hawki.aiHandle'), '@'),
            'hawkiUserDisplayName' => $hawkiUser->name,
            'hawkiUserUsername' => $hawkiUser->username,
            'hawkiUserAvatar' => StoredFileIdentifier::tryFromUserAvatar($hawkiUser)
        ]);
    }
}
