<?php

namespace App\Services\Chat\AiConv;

use App\Http\Resources\Legacy\AiConvResource;
use App\Models\AiConv;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class AiConvService
{

    public function __construct(
        protected PrivateMessageHandler $messageHandler
    )
    {
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
            'system_prompt' => $validatedData['system_prompt'],
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

        return $conv->toResource(AiConvResource::class)->resolve();
    }


    public function update($requestData, $slug): bool
    {
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        if ($conv->user_id !== $user->id) {
            throw new AuthorizationException();
        }

        try {
            $data = [];
            if (!empty($requestData['conv_name'])) {
                $data['conv_name'] = $requestData['conv_name'];
            }
            if (!empty($requestData['system_prompt'])) {
                $data['system_prompt'] = $requestData['system_prompt'];
            }
            if (!empty($data)) {
                $conv->update($data);
            }
            return true;
        } catch (Exception $e) {
            Log::error("Failed to update Conv. Error: $e");
            return false;
        }
    }


    public function delete($slug)
    {
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        if ($conv->user_id !== $user->id) {
            throw new AuthorizationException();
        }
        try {
            // Delete related messages and members
            $messages = $conv->messages()->get();
            foreach ($messages as $message) {
                $this->messageHandler->delete($conv, $message->toArray());
            }

            $conv->delete();
            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove Conv. Error: $e");
            return false;
        }
    }

}
