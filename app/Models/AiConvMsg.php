<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConvMsg extends Model
{
    use HasFactory;

    protected $fillable = [
        'conv_id',
        'user_id',
        'message_role',
        'message_id',
        'model',
        'iv',
        'tag',
        'content',
        'completion',
    ];

    // Define the relationship with AiConv
    public function conversation()
    {
        return $this->belongsTo(AiConv::class, 'conv_id');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

}
