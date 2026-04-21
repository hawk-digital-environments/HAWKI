<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_id_maps', static function (Blueprint $table) {
            $table->id();
            $table->string('model_id')->unique();
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('ai_model_id_maps');
    }
};
