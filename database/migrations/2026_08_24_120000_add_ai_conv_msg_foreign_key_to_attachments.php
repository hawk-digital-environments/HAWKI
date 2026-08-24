<?php

declare(strict_types=1);

use App\Models\AiConvMsg;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a concrete message reference beside the polymorphic columns. This lets
 * the database cascade private-chat attachments without affecting room attachments.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('attachments', static function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_conv_msg_id')->nullable()->after('attachable_type');
        });

        DB::table('attachments')
            ->where('attachable_type', AiConvMsg::class)
            ->whereIn('attachable_id', DB::table('ai_conv_msgs')->select('id'))
            ->update(['ai_conv_msg_id' => DB::raw('attachable_id')]);

        Schema::table('attachments', static function (Blueprint $table): void {
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
