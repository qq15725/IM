<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $table = 'channels';

    protected $fillable = [
        'id', 'user_id', 'name'
    ];

    const UPDATED_AT = null;
}