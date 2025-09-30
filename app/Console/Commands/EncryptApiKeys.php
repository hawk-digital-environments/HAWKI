<?php

namespace App\Console\Commands;

use App\Models\ApiProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:encrypt-keys {--dry-run : Show what would be encrypted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing plain-text API keys in the api_providers table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🔐 Scanning API providers for unencrypted keys...');
        
        // Get all providers directly from database to avoid automatic decryption
        $providers = DB::table('api_providers')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->get();

        if ($providers->isEmpty()) {
            $this->info('✅ No API providers with keys found');
            return Command::SUCCESS;
        }

        $encrypted = 0;
        $alreadyEncrypted = 0;
        $errors = 0;

        foreach ($providers as $provider) {
            // Check if the key is already encrypted by trying to decrypt it
            $isAlreadyEncrypted = $this->isEncrypted($provider->api_key);
            
            if ($isAlreadyEncrypted) {
                $alreadyEncrypted++;
                $this->line("  ✓ {$provider->provider_name}: Already encrypted");
                continue;
            }

            // Key is plain text, needs encryption
            if ($dryRun) {
                $this->line("  🔄 {$provider->provider_name}: Would encrypt plain-text key");
                $encrypted++;
                continue;
            }

            try {
                // Encrypt the plain text key
                $encryptedKey = Crypt::encryptString($provider->api_key);
                
                // Update in database directly to avoid model casting
                DB::table('api_providers')
                    ->where('id', $provider->id)
                    ->update(['api_key' => $encryptedKey]);
                
                $encrypted++;
                $this->line("  🔐 {$provider->provider_name}: Encrypted successfully");
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ❌ {$provider->provider_name}: Failed to encrypt - {$e->getMessage()}");
            }
        }

        // Summary
        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("  • Total providers: " . $providers->count());
        $this->line("  • Already encrypted: {$alreadyEncrypted}");
        $this->line("  • " . ($dryRun ? "Would encrypt" : "Encrypted") . ": {$encrypted}");
        
        if ($errors > 0) {
            $this->line("  • Errors: {$errors}");
        }

        if ($dryRun && $encrypted > 0) {
            $this->newLine();
            $this->comment('💡 Run without --dry-run to actually encrypt the keys');
        }

        if (!$dryRun && $encrypted > 0) {
            $this->newLine();
            $this->info('✅ API key encryption completed successfully!');
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a value is already encrypted
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }
}
