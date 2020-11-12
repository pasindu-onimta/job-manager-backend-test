<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = [];

    public function job()
    {
        return $this->belongsTo('App\Job');
    }

    public function users()
    {
        return $this->belongsToMany('App\User')->withPivot('id','plan_date');
    }

    public function activities()
    {
        return $this->hasMany('App\Activity');
    }

    public function subtasks()
    {
        return $this->hasMany('App\SubTasks');
    }

    public function jobs_by_division($division_id)
    {
        return $this->belongsTo('App\Job')->where('division_id',$division_id);
    }
}
