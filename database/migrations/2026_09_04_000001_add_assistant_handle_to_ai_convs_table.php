<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('ai_convs', static function (Blueprint $table): void {
            $table->string('assistant_handle')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_convs', static function (Blueprint $table): void {
            $table->dropColumn('assistant_handle');
        });
    }
};
