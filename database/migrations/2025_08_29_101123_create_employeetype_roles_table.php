<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employeetype_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employeetype_id')->constrained('employeetypes')->onDelete('cascade');
            $table->unsignedInteger('role_id')->comment('Reference to orchid roles table');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->boolean('is_primary')->default(false)->comment('Primary role assignment');
            $table->timestamps();

            // Unique constraint: one employeetype can only be assigned to one role as primary
            $table->unique(['employeetype_id', 'role_id'], 'unique_employeetype_role');

            // Index for performance
            $table->index(['employeetype_id', 'is_primary']);
            $table->index('role_id');
        });

        // Create default mapping for guest users to guest role
        $guestEmployeetypeId = DB::table('employeetypes')->where('raw_value', 'guest')->value('id');
        $guestRoleId = DB::table('roles')->where('slug', 'guest')->value('id');

        if ($guestEmployeetypeId && $guestRoleId) {
            DB::table('employeetype_roles')->insert([
                'employeetype_id' => $guestEmployeetypeId,
                'role_id' => $guestRoleId,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employeetype_roles');
    }
};
