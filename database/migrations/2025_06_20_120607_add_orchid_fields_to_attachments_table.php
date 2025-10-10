<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DISABLED: This migration is no longer needed as we use separate orchid_attachments table
        // See: 2025_09_18_160516_create_orchid_attachments_table.php

        // This migration has been superseded by the separate Orchid attachment system
        // No operations needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // DISABLED: This migration is no longer needed as we use separate orchid_attachments table

        // No operations needed as this migration was never applied
    }
};
