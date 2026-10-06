<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Marks a conversation that was branched off another one (see the chat
     * frontend's "branch in new chat" action). Deleting the origin must not
     * delete its branches — they are independent conversations — so the
     * self-referencing foreign key only clears the marker.
     */
    public function up(): void
    {
        Schema::table('ai_convs', function (Blueprint $table) {
            $table->string('branched_from_slug')->nullable()->after('system_prompt');
            $table->foreign('branched_from_slug')->references('slug')->on('ai_convs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_convs', function (Blueprint $table) {
            $table->dropForeign(['branched_from_slug']);
            $table->dropColumn('branched_from_slug');
        });
    }
};
