<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a generated reference for private-chat messages so the database can
 * cascade their attachments without relying on Eloquent model events.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('attachments', static function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_conv_msg_id')
                ->nullable()
                ->storedAs("CASE WHEN attachable_type = 'App\\\\Models\\\\AiConvMsg' THEN attachable_id ELSE NULL END")
                ->after('attachable_type');

            $table->foreign('ai_conv_msg_id')
                ->references('id')
                ->on('ai_conv_msgs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', static function (Blueprint $table): void {
            $table->dropForeign(['ai_conv_msg_id']);
            $table->dropColumn('ai_conv_msg_id');
        });
    }
};
