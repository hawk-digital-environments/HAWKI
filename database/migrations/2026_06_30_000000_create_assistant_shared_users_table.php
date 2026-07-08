<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_shared_users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assistant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_shared_users');
    }
};
