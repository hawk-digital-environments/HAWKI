<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('config_values', static function (Blueprint $table): void {
            $table->id();
            $table->string('namespace')->comment('The owning package: "hawki-core" for core configs, the plugin slug (e.g. "hawk-deepl-plugin") for plugin configs.');
            $table->string('key')->comment('The config property name. Must be unique within the namespace.');
            $table->text('value')->nullable()->comment('The serialized value. Encryption is handled by the cast layer, so encrypted values are stored as strings here.');
            $table->timestamps();
            $table->unique(['namespace', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_values');
    }
};
