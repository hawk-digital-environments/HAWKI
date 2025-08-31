<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConvMsgAux extends Model
{
    use HasFactory;

    protected $fillable = [
        'msg_id',
        'user_id',
        'type',
        'iv',
        'tag',
        'content',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    // Define the relationship with AiConv
    public function message()
    {
        return $this->belongsTo(AiConvMsg::class, 'msg_id');
    }

}
