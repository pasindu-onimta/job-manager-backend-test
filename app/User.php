<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasRoles;

    protected $guarded = [];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'employee_id', 'default_password',
        // 'user_type',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->hasOne('App\Employee');
    }

    public function usertype()
    {
        return $this->belongsTo('App\UserType', 'user_type_id');
    }

    public function jobs()
    {
        return $this->belongsToMany('App\Job');
    }

    public function pendingJobs()
    {
        return $this->belongsToMany('App\Job')->wherePivot('status', '!=', 6)->withPivot('status', 'id');
        return $this->belongsToMany('App\Job');
    }
    public function jobsWithPendingTasks()
    {
        return $this->belongsToMany('App\Job')->with('pendingTasks')->wherePivot('status', '!=', 6)->withPivot('status', 'id');
        return $this->belongsToMany('App\Job');
    }

    public function jobsActiveAll()
    {
        return $this->belongsToMany('App\Job')->wherePivot('status', 1);
    }
    public function jobsActive($job_type)
    {
        return $this->belongsToMany('App\Job')->wherePivot('status', 1)->wherePivot('jobtype_id', $job_type)->withPivot('qc_enable');
    }

    public function jobsUser($job_id, $user_id)
    {
        return $this->belongsToMany('App\Job')->wherePivot('status', 1)->wherePivot('job_id', $job_id)->wherePivot('user_id', $user_id)->withPivot('jobtype_id', 'qc_enable');
    }

    public function tasks()
    {
        return $this->belongsToMany('App\Task')->withPivot('plan_date', 'end_date', 'id');
    }

    public function plannedTasks()
    {
        return $this->belongsToMany('App\Task')
            ->withPivot('plan_date', 'end_date', 'id', 'qc_id', 'all_day', 'due_date')
            ->wherePivot('plan_date', '!=', null);
        // ->wherePivot('status', '!=', 6);
    }

    public function tasksActiveAll()
    {
        return $this->belongsToMany('App\Task')->wherePivot('status', 1);
    }

    public function tasksJobActiveAll($job_user_id)
    {
        return $this->belongsToMany('App\Task')->wherePivot('status', 1)->wherePivot('job_user_id', $job_user_id)->wherePivot('qc_id', '!=', 0)->withPivot('id');
    }

    public function subtasks()
    {
        return $this->hasMany('App\subTasks');
    }

    public function notifications()
    {
        return $this->hasMany('App\Notification');
    }

    public function division()
    {
        return $this->hasOne('App\Division');
    }

    public function activity_plan()
    {
        return $this->hasMany('App\ActivityPlan');
    }
}
