<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Msg extends Model
{
    protected $table = 'msgs';

    protected $fillable = [
        'id', 'user_id', 'data'
    ];

    const UPDATED_AT = null;
}