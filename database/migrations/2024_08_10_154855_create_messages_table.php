<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            
            $table->string('message_id');
            
            $table->string('message_role');
            
            $table->string('model')->nullable();
            
            $table->string('iv');
            
            $table->string('tag');
            
            $table->longText('content');
            
            $table->json('reader_signs')->nullable(); 
            
            $table->timestamps();
            
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
}