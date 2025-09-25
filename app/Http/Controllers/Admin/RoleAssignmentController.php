<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employeetype;
use App\Models\EmployeetypeRole;
use Illuminate\Http\JsonResponse;

class RoleAssignmentController extends Controller
{
    /**
     * Make a role assignment primary
     */
    public function makePrimary(EmployeetypeRole $assignment): JsonResponse
    {
        try {
            // Remove primary flag from all other assignments for this employeetype
            EmployeetypeRole::where('employeetype_id', $assignment->employeetype_id)
                ->update(['is_primary' => false]);

            // Set this assignment as primary
            $assignment->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Assignment set as primary successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error setting assignment as primary: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a role assignment
     */
    public function removeAssignment(EmployeetypeRole $assignment): JsonResponse
    {
        try {
            $assignment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assignment removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing assignment: '.$e->getMessage(),
            ], 500);
        }
    }
}
