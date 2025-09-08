<?php

namespace App\Console\Commands;

use App\Services\Chat\Attachment\AttachmentService;
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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(AttachmentService $attachmentService,
                           FileStorageService $fileStorageService)
    {
        $deleteInterval = env('DELETE_FILES_AFTER_MONTHS');
        if(empty($deleteInterval)){
            $this->info('File Storage cleanup schedule not set.');
            return;
        }

        $timeLimit = Carbon::now()->subMinutes(1);

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

    }
}
