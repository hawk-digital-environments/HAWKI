<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes the polymorphic relationship columns nullable so that attachments
     * can exist without being associated with a specific message. This is needed
     * for API-uploaded attachments that are referenced by UUID in AI requests.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('attachable_id')->nullable()->change();
            $table->string('attachable_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('attachable_id')->nullable(false)->change();
            $table->string('attachable_type')->nullable(false)->change();
        });
    }
};
