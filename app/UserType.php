<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->hasMany('App\User', 'user_type_id');
    }
}
