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
     * This migration updates LDAP setting keys to match the new naming convention.
     * 
     * Changes:
     * - ldap_connections.default.ldap_search_dn → ldap_connections.default.ldap_base_dn (search base remains base_dn)
     * - ldap_connections.default.ldap_base_dn → ldap_connections.default.ldap_bind_dn (bind DN renamed to bind_dn)
     * 
     * Note: In the old config structure, ldap_base_dn was used for binding,
     * and ldap_search_dn was used for searching. This was confusing.
     * Now: ldap_bind_dn for binding, ldap_base_dn for searching (base DN).
     */
    public function up(): void
    {
        // Check if migration already applied (both target keys exist)
        $bindDnExists = DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_bind_dn')
            ->exists();
            
        $baseDnExists = DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_base_dn')
            ->exists();
            
        if ($bindDnExists && $baseDnExists) {
            // Migration already applied, clean up old/temp keys if they exist
            DB::table('app_settings')
                ->whereIn('key', [
                    'ldap_connections.default.ldap_base_dn_temp',
                    'ldap_connections.default.ldap_search_dn',
                ])
                ->delete();
            return;
        }
        
        // Clean up temp key from previous failed attempts before starting
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_base_dn_temp')
            ->delete();

        // First, create a temporary key to avoid conflicts
        // Move old ldap_base_dn (bind DN) to temporary location
        $movedRows = DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_base_dn')
            ->update(['key' => 'ldap_connections.default.ldap_base_dn_temp']);

        // Now rename ldap_search_dn to ldap_base_dn (this becomes the new search base)
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_search_dn')
            ->update([
                'key' => 'ldap_connections.default.ldap_base_dn',
                'description' => 'Base DN for LDAP searches (search base)',
            ]);

        // Finally, rename temp to ldap_bind_dn (only if we moved something to temp)
        if ($movedRows > 0) {
            DB::table('app_settings')
                ->where('key', 'ldap_connections.default.ldap_base_dn_temp')
                ->update([
                    'key' => 'ldap_connections.default.ldap_bind_dn',
                    'description' => 'Distinguished Name (DN) used for bind operation (authentication)',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the process
        // Create temp for current ldap_base_dn
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_base_dn')
            ->update(['key' => 'ldap_connections.default.ldap_base_dn_temp']);

        // Rename ldap_bind_dn back to ldap_base_dn
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_bind_dn')
            ->update([
                'key' => 'ldap_connections.default.ldap_base_dn',
                'description' => 'Distinguished Name (DN) used for bind operation',
            ]);

        // Rename temp back to ldap_search_dn
        DB::table('app_settings')
            ->where('key', 'ldap_connections.default.ldap_base_dn_temp')
            ->update([
                'key' => 'ldap_connections.default.ldap_search_dn',
                'description' => 'Base DN for the LDAP search',
            ]);
    }
};
