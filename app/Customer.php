<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function branches()
    {
        return $this->hasMany('App\Branch')->where('status', 1);
    }
}
