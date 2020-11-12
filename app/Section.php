<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    public function jobtypes()
    {
        return $this->belongsToMany('App\Jobtype');
    }
}
