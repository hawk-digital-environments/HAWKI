<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('assistant_share_links', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // SHA-256 of the link's token; the plain token only ever leaves the server once.
            $table->string('token_hash', 64)->unique();
            $table->boolean('can_edit')->default(false);
            $table->timestamp('expires_at')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_share_links');
    }
};
