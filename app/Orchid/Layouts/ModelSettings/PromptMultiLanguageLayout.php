<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class PromptMultiLanguageLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        $prompt = $this->query->get('prompt');
        
        // Get existing German version if we're editing a prompt group
        $germanVersion = null;
        
        if ($prompt && $prompt->exists) {
            // Find German version of this prompt
            $germanVersion = AiAssistantPrompt::where('title', $prompt->title)
                ->where('language', 'de_DE')
                ->first();
        }

        return [
            // German Version
            TextArea::make('content_de')
                ->title('German Content')
                ->placeholder('Enter the German prompt text...')
                ->help('The complete German prompt text for AI assistants')
                ->value($germanVersion ? $germanVersion->content : '')
                ->rows(8)
                ->required(),
        ];
    }
}