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
        Schema::create('passkey_backups', function (Blueprint $table) {
            $table->id();

            $table->string('username');

            $table->text('ciphertext');

            $table->string('iv');
            
            $table->string('tag');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkey_backup');
    }
};
