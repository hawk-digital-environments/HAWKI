<?php

namespace App\Http\Controllers;

use App\Services\Announcements\AnnouncementService;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    protected $announcementService;

    public function __construct(AnnouncementService $announcementService)
    {
        $this->announcementService = $announcementService;
    }

    /**
     * Render announcement content for display
     */
    public function render(Request $request, int $id)
    {
        $user = Auth::user();
        $announcement = $this->announcementService->getAnnouncementForUser($user, $id);

        if (!$announcement) {
            return response('Announcement not found or unauthorized', 404);
        }
        $view = $this->announcementService->renderAnnouncement($announcement);
        return response()->json([
            'success' => true,
            'view'=> $view
        ], 200);
    }

    /**
     * Mark announcement as seen
     */
    public function markSeen(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $success = $this->announcementService->markAnnouncementAsSeen($user, $id);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Announcement marked as seen' : 'Failed to mark announcement as seen'
        ], $success ? 200 : 400);
    }

    /**
     * Mark announcement as accepted
     */
    public function submitReport(int $id): JsonResponse
    {
        // $validate
        $user = Auth::user();
        $success = $this->announcementService->markAnnouncementAsAccepted($user, $id);
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Announcement marked as accepted' : 'Failed to mark announcement as accepted'
        ], $success ? 200 : 400);
    }


    public function fetchLatestPolicy(){

        $announcement = $this->announcementService->fetchLatestPolicy();
        $view = $this->announcementService->renderAnnouncement($announcement);
        return response()->json([
            'success'=>true,
            'announcement'=>$announcement,
            'view'=>$view
        ]);

    }

}
