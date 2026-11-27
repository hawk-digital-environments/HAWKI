<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Share links were replaced by the exclusive release stage and co-authors;
 * installs that already ran the original create migration drop the table here.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('assistant_share_links');
    }

    public function down(): void
    {
    }
};
