<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('user_setting_values', static function (Blueprint $table): void {
            $table->id();
            $table->string('namespace')->comment('The owning package: "hawki-core" for core settings, the plugin slug (e.g. "hawk-deepl-plugin") for plugin settings.');
            $table->string('key')->comment('The name of the setting. Must be unique within the namespace for a given user.');
            $table->text('value')->nullable()->comment('The serialized value of the setting. Encryption is handled by the cast layer, so encrypted values are stored as strings here.');
            $table->foreignIdFor(User::class);
            $table->timestamps();
            $table->unique(['user_id', 'namespace', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_setting_values');
    }
};
