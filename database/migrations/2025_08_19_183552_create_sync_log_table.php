<?php

use App\Services\SyncLog\Value\SyncLogEntryAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_logs', static function (Blueprint $table) {
            $table->string('type')->index();
            $table->enum('action', [
                SyncLogEntryAction::SET->value,
                SyncLogEntryAction::REMOVE->value,
            ]);
            $table->integer('target_id');
            $table->integer('user_id');
            $table->integer('room_id')->nullable()->index();
            $table->string('transient_id')->nullable()->index();
            $table->text('transient_data')->nullable();
            $table->dateTime('updated_at', 6)->index();
            
            $table->unique(['type', 'target_id', 'user_id', 'room_id']);
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
