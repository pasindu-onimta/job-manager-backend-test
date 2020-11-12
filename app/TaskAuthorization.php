<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAuthorization extends Model
{
    protected $guarded = [];
    
    public function activity()
    {
        return $this->belongsTo('App\Activity');
    }
}
