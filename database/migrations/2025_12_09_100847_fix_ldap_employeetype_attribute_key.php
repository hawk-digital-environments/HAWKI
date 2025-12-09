<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes the LDAP employeetype attribute mapping key.
     * The config/ldap.php uses 'employeeType' (CamelCase), but older versions
     * had 'employeetype' (lowercase), causing the attribute mapping to fail.
     * 
     * On fresh installations: Does nothing (correct key already exists from seeder)
     * On existing installations: Renames the old key to the correct format
     */
    public function up(): void
    {
        // Only update if the old lowercase key exists
        // This makes the migration safe for both new and existing installations
        $oldKeySetting = DB::table('app_settings')
            ->where('key', 'ldap_connections.default.attribute_map.employeetype')
            ->first();

        if ($oldKeySetting) {
            // Old key exists, rename it to the correct CamelCase format
            DB::table('app_settings')
                ->where('key', 'ldap_connections.default.attribute_map.employeetype')
                ->update([
                    'key' => 'ldap_connections.default.attribute_map.employeeType',
                    'description' => 'EmployeeType Key Name Override',
                ]);
        }
        // If old key doesn't exist, do nothing (new installation already has correct key)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to lowercase
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.attribute_map.employeeType')
            ->update([
                'key' => 'ldap_connections.default.attribute_map.employeetype',
                'description' => 'Employeetype Key Name Override',
            ]);
    }
};
