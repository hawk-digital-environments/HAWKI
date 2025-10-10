<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class PromptEnglishLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        $prompt = $this->query->get('prompt');
        
        // Get existing English version if we're editing a prompt group
        $englishVersion = null;
        
        if ($prompt && $prompt->exists) {
            // Find English version of this prompt
            $englishVersion = AiAssistantPrompt::where('title', $prompt->title)
                ->where('language', 'en_US')
                ->first();
        }

        return [
            // English Version  
            TextArea::make('content_en')
                ->title('English Content')
                ->placeholder('Enter the English prompt text...')
                ->help('The complete English prompt text for AI assistants')
                ->value($englishVersion ? $englishVersion->content : '')
                ->rows(8)
                ->required(),
        ];
    }
}