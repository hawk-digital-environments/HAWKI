<?php

namespace App\Console\Commands;

use App\Services\Chat\Attachment\AttachmentService;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Console\Command;

use App\Services\Storage\FileStorageService;
use App\Models\Attachment;
use Carbon\Carbon;

class CleanupFileStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filestorage:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired files from the storage.';

    /**
     * Execute the console command.
     */
    public function handle(AttachmentService $attachmentService,
                           FileStorageService $fileStorageService)
    {
        $deleteInterval = config('filesystems.garbage_collections.remove_files_after_months');
        if($deleteInterval == 0){
            $this->info('File Storage cleanup is disabled.');
            return;
        }

        $timeLimit = Carbon::now()->subMonths($deleteInterval);

        //DELETE ATTACHMENTS
        $attachments = Attachment::where('created_at', '<', $timeLimit)->get();

        if(count($attachments) > 0){
            $failsList = [];
            $successCount = 0;
            $this->line("Removing Expired attachments");
            $this->line(count($attachments) . " expired Attachments were found.");
            foreach($attachments as $atch){
                $deleted = $attachmentService->delete($atch);
                if(!$deleted){
                    $failsList[] = $atch;
                }
                else{
                    $successCount++;
                }
            }
            if(count($failsList) > 0){
                $this->line("Following Attachments could not be deleted:");
                foreach($failsList as $fail){
                    $this->line("$fail->name ( $fail->uuid ) could not be removed.");
                }
            }

            $this->info($successCount . " of " . count($attachments) . " where deleted");
        }
        else{
            $this->info("No expired Attachment found");
        }

        $this->line('Cleaning up temp files...');

        $fileStorage = app(FileStorageService::class);
        $success = $fileStorage->deleteTempExpiredFiles();
        if($success){
            $this->info("Temp files deleted from File Storage.");
        }
        else{
            $this->error("Could not delete temp files, or no expired files were found.");
        }

    }
}
