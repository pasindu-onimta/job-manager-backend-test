<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Activity extends Model
{
    // use LogsActivity;

    protected $guarded = [];

    // protected static $logAttributes = ['section_id', 'user_id'];

    public function task()
    {
        return $this->belongsTo('App\Task');
    }

    public function authorizations()
    {
        return $this->hasMany('App\TaskAuthorization');
    }
}
