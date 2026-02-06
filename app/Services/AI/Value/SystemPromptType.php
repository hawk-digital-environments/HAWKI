<?php

namespace App\Services\AI\Value;

enum SystemPromptType: string
{
    case DEFAULT = 'Default_Prompt';
    case SUMMARY = 'Summery_Prompt';
    case IMPROVEMENT = 'Improvement_Prompt';
    case NAME = 'Name_Prompt';
}
