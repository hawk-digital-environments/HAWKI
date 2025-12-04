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
        ?array $targetRoles = null,
        ?string $anchor = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
        bool $isPublished = false
    ): Announcement {
        return Announcement::create([
            'is_published' => $isPublished,
            'title' => $title,
            'view' => $view,
            'type' => $type,
            'is_forced' => $isForced,
            'is_global' => $isGlobal,
            'target_roles' => $targetRoles,
            'anchor'=>$anchor,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function getUserAnnouncements(){
        $announcements = Auth::user()->unreadAnnouncements();

        // Filter to only include published announcements
        $announcements = $announcements->filter(function($announcement) {
            return $announcement->is_published == true;
        });

        // Collect force announcements
        $forceAnnouncements = [];
        foreach ($announcements as $announcement) {
            if ($announcement->is_forced == true && $announcement->anchor == null) {
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
                'anchor'=>$ann->anchor,
                'expires_at'=>$ann->expires_at
            ];
        });
    }

    public function getAllNews() {
        return Announcement::query()->where(['type' => 'news', 'is_published' => true])->orderByDesc('starts_at')->paginate(10);
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

        // For non-global announcements, check if user has any of the target roles
        if (!empty($announcement->target_roles)) {
            $userRoles = $user->getRoles()->pluck('slug')->toArray();
            $targetRoles = $announcement->target_roles;

            // Check if user has at least one of the target roles
            return !empty(array_intersect($userRoles, $targetRoles));
        }

        return false;
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
    public function renderAnnouncement(Announcement $announcement): string
    {
        $lang = Session::get('language')['id'];

        // Try to get translation from database first
        $translation = $announcement->getTranslation($lang);

        if ($translation) {
            return $translation->content;
        }

        // Fallback to file-based system for backwards compatibility
        $view = $announcement->view;
        $file = resource_path("announcements/$view/$lang.md");

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        // If no translation found, try English as fallback
        $translation = $announcement->getTranslation('en_US');
        if ($translation) {
            return $translation->content;
        }

        // Last resort: check English file
        $fallbackFile = resource_path("announcements/$view/en_US.md");
        if (file_exists($fallbackFile)) {
            return file_get_contents($fallbackFile);
        }

        return "# Content not available\n\nNo translation found for this announcement.";
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
