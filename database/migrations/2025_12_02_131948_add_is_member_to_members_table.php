<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Add isMember column - true when user is actively in the room
            // Default true for existing members (they are all currently active)
            $table->boolean('isMember')->default(true)->after('role');
            
            // Ensure isRemoved exists and has correct default
            // isRemoved tracks if user was kicked/left - false by default
            if (!Schema::hasColumn('members', 'isRemoved')) {
                $table->boolean('isRemoved')->default(false)->after('isMember');
            }
        });
        
        // Set all existing members to proper state
        // Existing members: isMember=1, isRemoved=0 (active members)
        DB::table('members')->update([
            'isMember' => true,
            'isRemoved' => false
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('isMember');
        });
    }
};
