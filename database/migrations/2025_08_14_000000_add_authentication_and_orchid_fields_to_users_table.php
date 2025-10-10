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
        Schema::table('users', function (Blueprint $table) {
            // Add password field for local authentication
            $table->string('password')->nullable()->after('email');

            // Add authentication type enum
            $table->enum('auth_type', ['ldap', 'oidc', 'shibboleth', 'local'])
                ->default('ldap')
                ->after('employeetype')
                ->comment('Authentication method used for this user');

            // Add password reset flag for local users
            $table->boolean('reset_pw')
                ->default(false)
                ->after('auth_type')
                ->comment('Whether user needs to reset their password during registration');

            // Add approval flag for user management
            $table->boolean('approval')
                ->default(true)
                ->after('reset_pw')
                ->comment('Whether user is approved for access');

            // Add Orchid permissions field
            $table->json('permissions')
                ->nullable()
                ->after('isRemoved')
                ->comment('Orchid permissions for admin access control');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'auth_type', 'reset_pw', 'approval', 'permissions']);
        });
    }
};
