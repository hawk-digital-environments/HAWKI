<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistantPrompt;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class PromptListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'prompts';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            // Primary Identifier - Prompt Group
            TD::make('title', 'Prompt Group')
                ->sort()
                ->cantHide()
                ->render(function (AiAssistantPrompt $prompt) {
                    $filterParams = request()->only([
                        'prompt_search', 
                        'prompt_status', 
                        'prompt_category',
                        'sort',
                        'filter'
                    ]);
                    
                    // For now, edit the first prompt in the group
                    $editUrl = route('platform.models.prompts.edit', $prompt);
                    if (!empty($filterParams)) {
                        $editUrl .= '?' . http_build_query($filterParams);
                    }
                    
                    // Clean up the title by removing the redundant ' Prompt' suffix
                    $cleanTitle = str_replace(' Prompt', '', $prompt->title);
                    $cleanTitle = str_replace('_', ' ', $cleanTitle);
                    
                    return Link::make($cleanTitle)->href($editUrl);
                }),

            // Description
            TD::make('description', 'Description')
                ->render(function (AiAssistantPrompt $prompt) {
                    if (!$prompt->description) {
                        return "<span class=\"text-muted\">No description</span>";
                    }
                    
                    $truncated = strlen($prompt->description) > 80 
                        ? substr($prompt->description, 0, 80) . '...'
                        : $prompt->description;
                    
                    return "<span title=\"{$prompt->description}\">{$truncated}</span>";
                }),

            // Languages - Show all available language versions
            TD::make('languages', 'Languages')
                ->render(function (AiAssistantPrompt $prompt) {
                    // Get all prompts in the same group
                    $variants = \App\Models\AiAssistantPrompt::where('category', $prompt->category)
                        ->where('title', $prompt->title)
                        ->get();
                    
                    $badges = [];
                    foreach ($variants as $variant) {
                        // Improved language detection
                        $language = 'Unknown';
                        $badgeClass = 'bg-secondary';
                        
                        $content = strtolower($variant->content);
                        
                        // German detection - check for common German words/phrases
                        if (str_contains($content, 'du bist') || 
                            str_contains($content, 'sie sind') ||
                            str_contains($content, 'deine') ||
                            str_contains($content, 'ihrer') ||
                            str_contains($content, 'hilfreicher') ||
                            str_contains($content, 'assistent') ||
                            str_contains($content, 'antwort') ||
                            str_contains($content, 'wörter')) {
                            $language = 'DE';
                            $badgeClass = 'bg-primary';
                        }
                        // English detection - check for common English words/phrases
                        elseif (str_contains($content, 'you are') || 
                                str_contains($content, "you're") ||
                                str_contains($content, 'your goal') ||
                                str_contains($content, 'your answer') ||
                                str_contains($content, 'helpful') ||
                                str_contains($content, 'assistant') ||
                                str_contains($content, 'words') ||
                                str_contains($content, 'abstract')) {
                            $language = 'EN';
                            $badgeClass = 'bg-success';
                        }
                        
                        $badges[] = "<span class=\"badge rounded-pill {$badgeClass} me-1\" title=\"Status: {$variant->status}\">{$language}</span>";
                    }
                    
                    return implode('', $badges) ?: '<span class="text-muted">No languages</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('120px'),

            // Category
            TD::make('category', 'Category')
                ->sort()
                ->render(function (AiAssistantPrompt $prompt) {
                    $categoryColors = [
                        'Default_Prompt' => 'bg-primary',
                        'Name_Prompt' => 'bg-info',
                        'Improvement_Prompt' => 'bg-success',
                        'Summery_Prompt' => 'bg-warning',
                        'general' => 'bg-secondary',
                        'system' => 'bg-primary',
                        'custom' => 'bg-info',
                        'template' => 'bg-success',
                    ];
                    
                    $badgeClass = $categoryColors[$prompt->category] ?? 'bg-secondary';
                    $cleanCategory = str_replace('_', ' ', $prompt->category);
                    $cleanCategory = str_replace(' Prompt', '', $cleanCategory);
                    
                    return "<span class=\"badge {$badgeClass} border-0 rounded-pill\">" . ucfirst($cleanCategory) . "</span>";
                }),





            // Creator
            TD::make('creator.name', 'Creator')
                ->render(function (AiAssistantPrompt $prompt) {
                    if ($prompt->creator) {
                        // Show "System" for HAWKI user
                        if ($prompt->creator->name === 'HAWKI') {
                            return "<span class=\"badge bg-primary border-0 rounded-pill\">System</span>";
                        }
                        return $prompt->creator->name;
                    }
                    return "<span class=\"text-muted\">Unknown</span>";
                })
                ->width('100px'),

            TD::make('updated_at', __('Last Updated'))
                ->render(function (AiAssistantPrompt $prompt) {
                    if (!$prompt->updated_at) {
                        return '<span class="text-muted">—</span>';
                    }
                    return '<div class="text-end">' . 
                           '<div>' . $prompt->updated_at->format('M j, Y') . '</div>' .
                           '<small class="text-muted">' . $prompt->updated_at->format('H:i') . '</small>' .
                           '</div>';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (AiAssistantPrompt $prompt) {
                    $filterParams = request()->only([
                        'prompt_search', 
                        'prompt_status', 
                        'prompt_category',
                        'sort',
                        'filter'
                    ]);
                    
                    $editUrl = route('platform.models.prompts.edit', $prompt->id);
                    if (!empty($filterParams)) {
                        $editUrl .= '?' . http_build_query($filterParams);
                    }
                    
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Link::make(__('Edit Group'))
                                ->href($editUrl)
                                ->icon('bs.pencil')
                                ->title('Edit all prompts in this group'),

                            Button::make(__('Delete Group'))
                                ->icon('bs.trash3')
                                ->confirm(__('Are you sure you want to delete ALL prompts in this group? This cannot be undone.'))
                                ->method('remove', ['id' => $prompt->id])
                                ->title('Delete all variants in this group'),
                        ]);
                }),
        ];
    }
}