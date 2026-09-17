<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('assistant_attachments', static function (Blueprint $table): void {
            $table->unsignedBigInteger('size')->nullable()->after('mime');
            // Admin judgment on the uploaded file: null = not yet reviewed.
            $table->string('review_status')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_attachments', static function (Blueprint $table): void {
            $table->dropColumn(['size', 'review_status']);
        });
    }
};
