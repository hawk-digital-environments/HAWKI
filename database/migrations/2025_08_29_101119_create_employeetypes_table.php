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
        Schema::create('employeetypes', function (Blueprint $table) {
            $table->id();
            $table->string('raw_value')->comment('Original value from auth system (e.g., "1", "employee", "staff")');
            $table->string('auth_method')->comment('Authentication method: LDAP, OIDC, Shibboleth');
            $table->string('display_name')->comment('Human-readable name for admin interface');
            $table->boolean('is_active')->default(true)->comment('Whether this mapping is active');
            $table->text('description')->nullable()->comment('Optional description for this employeetype');
            $table->timestamps();

            // Unique constraint: same raw_value can exist for different auth_methods
            $table->unique(['raw_value', 'auth_method'], 'unique_raw_value_auth_method');

            // Index for fast lookups
            $table->index(['auth_method', 'is_active']);
        });

        // Insert default guest entry
        DB::table('employeetypes')->insert([
            'raw_value' => 'guest',
            'auth_method' => 'system',
            'display_name' => 'Guest User',
            'is_active' => true,
            'description' => 'Default role for unknown employeetype values',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employeetypes');
    }
};
