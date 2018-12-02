<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChannelMsg extends Model
{
    protected $table = 'channel_msg';

    protected $fillable = [
        'id', 'user_id', 'channel_id', 'msg_id'
    ];

    const UPDATED_AT = null;
}