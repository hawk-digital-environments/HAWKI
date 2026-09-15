<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration records deleted in Administration, keyed like the deployment files name them,
 * so imports do not recreate them. See App\Services\Admin\DeletedRecords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_deleted_records', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 50);
            // MCP server urls can exceed the index key length, so the unique key hashes the identity.
            $table->text('identity');
            $table->char('identity_hash', 64);
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['resource', 'identity_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_deleted_records');
    }
};
