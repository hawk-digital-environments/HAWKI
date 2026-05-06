<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::table('assistants', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'HAWKI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userIds = DB::table('users')->pluck('id');
        $now = now();

        DB::table('organization_user')->insert(
            $userIds->map(fn($id) => [
                'organization_id' => $orgId,
                'user_id' => $id,
                'role' => 'member',
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray()
        );
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
