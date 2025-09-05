<?php

namespace App\Services\Chat\AiConv;

use App\Models\AiConv;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Chat\Attachment\AttachmentService;


class AiConvService{

    protected $messageHandler;
    protected $contentValidator;
    protected $attachmentService;

    public function __construct(AttachmentService $attachmentService,
                                MessageContentValidator $messageContentValidator)
    {
        $this->messageHandler = MessageHandlerFactory::create('private');
        $this->attachmentService = $attachmentService;
        $this->contentValidator = $messageContentValidator;
    }


    public function create(array $validatedData): AiConv
    {
        if (!$validatedData['conv_name']) {
            $validatedData['conv_name'] = 'New Chat';
        }

        return AiConv::create([
            'conv_name' => $validatedData['conv_name'],
            'user_id' => Auth::id(), // Associate the conversation with the user
            'slug' => Str::slug(Str::random(16)), // Create a unique slug
            'system_prompt'=> $validatedData['system_prompt'],
        ]);
    }

    public function load(string $slug): array
    {
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        // Example: Custom authorization logic
        if ($conv->user_id !== $user->id) {
            throw new AuthorizationException();
        }

        return [
            'id' => $conv->id,
            'name' => $conv->chat_name,
            'slug' => $conv->slug,
            'system_prompt' => $conv->system_prompt,
            'messages' => $conv->messageObjects()
        ];
    }


    public function update($requestData, $slug): bool{
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        if ($conv->user_id !== $user->id) {
            throw new AuthorizationException();
        }

        try{
            $conv->update(['system_prompt' => $requestData['system_prompt']]);
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to update Conv. Error: $e");
            return false;
        }
    }


    public function delete($slug){
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        // Check if the conv exists
        if (!$conv) {
            throw new ModelNotFoundException();
        }

        if ($conv->user_id !== $user->id) {
            throw new AuthorizationException();
        }
        try{
            // Delete related messages and members
            $conv->messages()->delete();

            $conv->delete();
            return true;
        }
        catch(Exception $e){
            Log::error("Failed to remove Conv. Error: $e");
            return false;
        }
    }

}
