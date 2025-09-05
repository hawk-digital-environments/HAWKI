<?php

namespace App\Services\AI\Providers;

/**
 * Alias class for GoogleProvider to handle naming convention fallback
 * This fixes the issue where deriveProviderClassFromApiFormat generates
 * "GoogleGenerativeLanguageProvider" but the actual class is "GoogleProvider"
 */
class GoogleGenerativeLanguageProvider extends GoogleProvider
{
    // This class simply extends GoogleProvider without any modifications
    // It serves as an alias to fix the naming convention mismatch
}
