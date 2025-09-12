<?php
declare(strict_types=1);


namespace App\Http\Controllers;


use App\Services\SyncLog\SyncLogProvider;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

class SyncLogController extends Controller
{
    public function index(
        Request         $request,
        SyncLogProvider $logProvider
    ): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $reqConstraints = $request->validate([
                'last-sync' => 'sometimes|date',
                'room-id' => 'sometimes|integer|exists:rooms,id',
                'offset' => 'sometimes|integer|min:0',
                'limit' => 'sometimes|integer|min:1|max:10000',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Invalid parameters', 'details' => $e->errors()], 400);
        }
        
        $syncLog = $logProvider->getLog(new SyncLogEntryConstraints(
            user: $user,
            lastSync: !empty($reqConstraints['last-sync'])
                ? Carbon::parse($reqConstraints['last-sync'], 'UTC')->setTimezone(config('app.timezone'))
                : null,
            offset: !empty($reqConstraints['offset']) ? (int)$reqConstraints['offset'] : null,
            limit: !empty($reqConstraints['limit']) ? (int)$reqConstraints['limit'] : null,
            roomId: !empty($reqConstraints['room-id']) ? (int)$reqConstraints['room-id'] : null,
        ));
        
        return response()->json($syncLog);
    }
    
}
