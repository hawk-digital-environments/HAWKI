<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchid\Platform\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('selfassign')->default(false)->after('permissions');
        });

        // Set existing roles to appropriate selfassign values
        $this->setSelfAssignValues();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('selfassign');
        });
    }

    /**
     * Set appropriate selfassign values for existing roles
     */
    private function setSelfAssignValues(): void
    {
        // Roles that users should be able to assign to themselves during registration
        $selfAssignableRoles = ['student', 'lecturer', 'staff', 'guest'];
        
        // Non-self-assignable roles (admin, etc.)
        $restrictedRoles = ['admin'];

        // Set selfassign = true for allowed roles
        Role::whereIn('slug', $selfAssignableRoles)->update(['selfassign' => true]);
        
        // Set selfassign = false for restricted roles (explicit, though default is false)
        Role::whereIn('slug', $restrictedRoles)->update(['selfassign' => false]);
    }
};
