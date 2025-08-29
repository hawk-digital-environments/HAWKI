<?php

namespace App\Services\Announcements;

use Illuminate\Support\Facades\Session;
use App\Models\Announcements\Announcement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Log;
use Exception;


class AnnouncementService
{
    /**
     * Create a new announcement
     *
     * Example:
     * $service->createAnnouncement('announcements.terms_update', 'info', true);
     */
    public function createAnnouncement(
        string $title,
        string $view,
        string $type = 'info',
        bool $isForced = false,
        bool $isGlobal = true,
        ?array $targetUsers = null,
        ?string $startsAt = null,
        ?string $expiresAt = null
    ): Announcement {
        Log::debug($isGlobal);
        return Announcement::create([
            'title' => $title,
            'view' => $view,
            'type' => $type,
            'is_forced' => $isForced,
            'is_global' => $isGlobal,
            'target_users' => $targetUsers,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function getUserAnnouncements(){
        $announcements = Auth::user()->unreadAnnouncements();
        Log::debug($announcements);

        // Collect force announcements
        $forceAnnouncements = [];
        foreach ($announcements as $announcement) {
            if ($announcement->is_forced === true) {
                $forceAnnouncements[] = $announcement;
            }
        }
        Session::put('force_announcements', $forceAnnouncements);
        return $announcements->map(function($ann){
            return[
                'id' =>$ann->id,
                'title'=>$ann->title,
                'type'=>$ann->type,
                'isForced'=>$ann->is_forced,
                'expires_at'=>$ann->expires_at
            ];
        });
    }



    /**
     * Find active announcements (system-wide)
     */
    public function getActiveAnnouncements(): Collection
    {
        $now = now();

        return Announcement::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->get();
    }



    public function fetchLatestPolicy(): Announcement{
        return $this->getActiveAnnouncements()->where('type', 'policy')->firstOrFail();
    }



    /**
     * Validate user access to announcement
     */
    public function validateUserAccess(User $user, Announcement $announcement): bool
    {
        if ($announcement->is_global) {
            return true;
        }

        // For non-global announcements, check if user is in the target list
        return $user->announcements()->where('announcement_id', $announcement->id)->exists();
    }

    /**
     * Get announcement for rendering with access validation
     */
    public function getAnnouncementForUser(User $user, int $announcementId): ?Announcement
    {
        $announcement = Announcement::find($announcementId);

        if (!$announcement) {
            return null;
        }

        if (!$this->validateUserAccess($user, $announcement)) {
            return null;
        }

        return $announcement;
    }


    /**
     * Render announcement Blade and return to frontend
     */

    public function renderAnnouncement(Announcement $announcement){
        $view = $announcement->view;
        $lang = Session::get('language')['id'];
        $file = resource_path("announcements/$view/$lang.md");
        $content = file_get_contents($file);
        return $content;
    }

    /**
     * Mark announcement as seen for user
     */
    public function markAnnouncementAsSeen(User $user, int $announcementId): bool
    {
        try {
            $announcement = Announcement::find($announcementId);
            if (!$announcement || !$this->validateUserAccess($user, $announcement)) {
                return false;
            }

            $user->markAnnouncementAsSeen($announcementId);
            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mark announcement as accepted for user
     */
    public function markAnnouncementAsAccepted(User $user, int $announcementId): bool
    {
        try {
            $announcement = Announcement::find($announcementId);
            if (!$announcement || !$this->validateUserAccess($user, $announcement)) {
                return false;
            }

            $user->markAnnouncementAsAccepted($announcementId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
