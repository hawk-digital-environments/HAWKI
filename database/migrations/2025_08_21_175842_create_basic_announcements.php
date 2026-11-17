<?php

use App\Services\Announcements\AnnouncementService;
use App\Services\Announcements\Values\AnnouncementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Models\Announcements\Announcement;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $service = app(AnnouncementService::class);

        // Check if required files exist
        if (!File::exists(resource_path('announcements/basic-guidelines/de_DE.md')) ||
            !File::exists(resource_path('announcements/basic-guidelines/en_US.md')) ||
            !File::exists(resource_path('announcements/first-upload/de_DE.md')) ||
            !File::exists(resource_path('announcements/first-upload/en_US.md'))
        ) { throw new RuntimeException("File not found. Check the basic announcement files in basic-guidelines and first-upload");}

        // Guidelines
        if (!Announcement::where('view', 'basic-guidelines')->first()) {
            $service->createAnnouncement(
                'guidelines',
                'basic-guidelines',
                AnnouncementType::POLICY,
                true,
                true,
                null,
                null,
                now()->toDateTimeString(),
                null,
            );
        }

        // First upload
        if (!Announcement::where('view', 'first-upload')->first()) {
            $service->createAnnouncement(
                'firstUpload',
                'first-upload',
                AnnouncementType::SYSTEM,
                true,
                true,
                null,
                "FileUpload",
                now()->toDateTimeString(),
                null,
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basic_announcements');
    }
};
