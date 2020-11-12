<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $guarded = [];

    public function tasks()
    {
        return $this->hasMany('App\Task');
    }
    public function pendingTasks()
    {
        return $this->hasMany('App\Task');
    }

    public function tasksOrderByTaskNo()
    {
        return $this->hasMany('App\Task')->orderBy('last_id');
    }

    public function users()
    {
        return $this->belongsToMany('App\User');
    }

    public function notifications()
    {
        return $this->hasMany('App\Notification');
    }

    public function division()
    {
        return $this->belongsTo('App\Division');
    }

    public function customer()
    {
        return $this->belongsTo('App\Customer');
    }

    public function employee()
    {
        return $this->belongsTo('App\User');
    }

    public function jobCoordinator()
    {
        return $this->belongsTo('App\User', 'jobCoordinator_id');
    }

    public function requestedEmployee()
    {
        return $this->belongsTo('App\User', 'requestedEmployee_id');
    }

    public function jobPriority()
    {
        return $this->belongsTo('App\JobPriority', 'priority');
    }

    // public function system(){
    //     return $this->hasOne('App\System')
    // }
}
