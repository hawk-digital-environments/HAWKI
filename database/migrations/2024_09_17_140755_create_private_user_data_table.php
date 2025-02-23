<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrivateUserDataTable extends Migration
{
    public function up()
    {
        Schema::create('private_user_data', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('KCIV');
            
            $table->string('KCTAG');

            $table->text('keychain');

            $table->timestamps();
            
        });
    }

    public function down()
    {
        Schema::dropIfExists('private_user_data');
    }
}