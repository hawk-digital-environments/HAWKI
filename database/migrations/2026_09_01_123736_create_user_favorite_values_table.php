<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorite_values', function (Blueprint $table) {
            $table->id();
$table->string('namespace');
$table->string('identifier');
$table->foreignIdFor(User::class);
$table->timestamps();
$table->unique(['namespace', 'identifier', 'user_id']);
$table->index(['id', 'user_id']);//
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_values');
    }
};
