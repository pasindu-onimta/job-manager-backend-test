<?php

namespace App\Http\Controllers\Kanban;

use App\Activity;
use App\ActivityPlan;
use App\AuthorizationRequest;
use App\Comment;
use App\Employee;
use App\Feature;
use App\Http\Controllers\Controller;
use App\Job;
use App\Notification;
use App\SubTasks;
use App\Task;
use App\User;
use App\JobDueDateChange;
use App\TaskAuthorization;
use FFI\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TasksController extends Controller
{
    public function index()
    {
        $job_id = request()->job_id;
        $job_user_id = request()->job_user_id;
        $job_type = request()->job_type;
        $user_id = auth()->user()->id;
        $data = [];
        $assigned_tasks = [];
        //TODO: add user restrictions
        $jobs_all = auth()->user()->jobsActiveAll;
        $jobs = User::find($user_id)->jobsActive($job_type)->orderBy('due_date', 'ASC')->get();
        //FIXME: load tasks error

        if ($job_type == 1) {
            $a_tasks = DB::table('task_user')->where(['job_user_id' => $job_user_id, ['qc_id', '!=', 0]])->whereNotIn('status', [0, 7])->get();
        } else {
            $a_tasks = DB::table('task_user')->where(['job_user_id' => $job_user_id, 'status' => 1, ['qc_id', '=', 0]])->get();
        }
        $isAllTasksApproved = false;
        if (!$a_tasks->isEmpty()) {
            foreach ($a_tasks as $key => $a_task) {
                $task = Task::find($a_task->task_id);
                $job = Task::find($a_task->task_id)->job;
                $task->isBugged = false;
                $on_qc_process = false;
                $qc_id = $a_task->qc_id;

                if (isset($a_task)) {
                    $task_qc_user_id = $a_task->id;
                    $task_qc_activity = Activity::where(['task_user_id' => $task_qc_user_id])->orderBy('id', 'DESC')->first();

                    if (isset($task_qc_activity)) {
                        if ($task_qc_activity->section_id == 3 || $task_qc_activity->section_id == 4) {
                            // $on_qc_process = true;
                        }
                    }
                    if (isset($task_qc_activity)) {
                        if ($task_qc_activity->section_id == 5 && $task_qc_activity->qc_status == 1) {
                            $task->isBugged = true;
                        }
                    }
                }
                $task->on_qc_process = $on_qc_process;
                // $task->job_no = $job->job_no;
                $task->task_user_id = $a_task->id;
                $task->task_due_date = $a_task->due_date;
                $task->task_plan_date = $a_task->plan_date;

                $task->contact_number = $job->contact_number;
                $task->contact_person = $job->contact_person;
                $task->hasAuthorized = true;





                // check and apply features
                if (Feature::find(1)->status == 1) {

                    $hasAuthorizedMain = TaskAuthorization::where('job_user_id', $a_task->job_user_id)
                        ->whereDate('created_at', now())
                        ->orderBy('created_at', 'DESC')
                        ->first();

                    if (!$hasAuthorizedMain) {
                        $task->hasAuthorized = false;
                    } else {
                        if ($hasAuthorizedMain->entire_job != 1) {
                            $hasAuthorized = TaskAuthorization::where('task_user_id', $a_task->id)->whereDate('created_at', now())
                                ->orderBy('created_at', 'DESC')
                                ->first();
                            if (!$hasAuthorized) {
                                $task->hasAuthorized = false;
                            }
                        } else {
                            $task->hasAuthorized = true;
                        }
                    }
                }









                array_push($assigned_tasks, $task);
            }
        }

        foreach ($assigned_tasks as $key => $task) {

            $activity = Activity::where([['task_user_id', '=', $task->task_user_id]])->get()->last();
            if (isset($activity)) {
                $task->current_section = $activity['section_id'];
            } else {
                $task->current_section = 1;
            }

            $task->customer_name = Job::find($job_id)->customer_name;
            array_push($data, $task);
        }
        // return $assigned_tasks;
        Notification::where(['user_id' => $user_id, 'job_id' => $job_id])->update(['status' => 0]);
        return $data;
    }

    public function single(Request $request)
    {
        $user_id = auth()->user()->id;
        $task_id = $request->task_id;
        $mode = $request->mode;
        $activitiesWithComments = [];
        $activities = DB::select("SELECT
                `activities`.`id`, `sections`.`section_name`, `activities`.`user_id`, `activities`.`is_qc_task`,
                (SELECT `section_name` FROM `sections` WHERE `id` = `activities`.`prev_section_id`) AS prev_section,
                (SELECT `name` FROM `users` WHERE `id` = `activities`.`user_id`) AS name,
                `activities`.`created_at` AS startTime,
                `activities`.`updated_at` AS endTime,
                `employees`.`image`
                FROM
                    `activities`
                    INNER JOIN `tasks` ON (`activities`.`task_id` = `tasks`.`id`)
                    INNER JOIN `sections` ON (`activities`.`section_id` = `sections`.`id`)
                    INNER JOIN jobs ON (`tasks`.`job_id` = `jobs`.`id`)
                    INNER JOIN users ON (`activities`.`user_id` = `users`.`id`)
                    INNER JOIN employees ON (`users`.`employee_id` = `employees`.`id`)
                    WHERE `tasks`.`id` = " . $task_id . " AND `activities`.`qc_status` = 0 ORDER BY `activities`.`id` DESC LIMIT 0, 50");
        // WHERE `tasks`.`id` = ".$task_id." AND `activities`.`user_id` = ".$user_id." ORDER BY `activities`.`id` DESC LIMIT 0, 5");

        $s_task = Task::where(['id' => $task_id])->first();
        $task_no = $s_task->task_no;
        $s_task_job_id = $s_task->job_id;
        // $first_task = substr($task_no, 0, 1);
        $first_task = explode(".", $task_no)[0];
        $t = Task::where(['job_id' => $s_task_job_id, 'task_no' => $first_task, 'Time01' => 0])->first();
        $task_title = '';
        if (isset($t)) {
            $task_title = $t->task_name;
        } else {
            $task_title = 'N/A';
        }
        if (!$activities) {
            $activity = ['task_title' => $task_title, 'fresh_task' => true];
            array_push($activitiesWithComments, $activity);
        }

        foreach ($activities as $key => $activity) {
            $comments = DB::select("SELECT users.id AS user_id, users.name, comments.created_at, comments.activity_id, comments.comment, comments.comment_type, employees.image FROM comments
            INNER JOIN users ON (comments.user_id = users.id)
            INNER JOIN activities ON (comments.activity_id = activities.id)
            INNER JOIN employees ON (`users`.`employee_id` = `employees`.`id`)
            WHERE activities.id = " . $activity->id . "
            ORDER BY comments.id DESC");

            if (isset($comments)) {
                foreach ($comments as $key => $comment) {
                    $comment->task_title = $task_title;
                    array_push($activitiesWithComments, $comment);
                }
            }
            $activity->task_title = $task_title;
            $activity->fresh_task = false;
            array_push($activitiesWithComments, $activity);
        }
        DB::enableQueryLog();
        if ($mode == 'qc') {
            // $details = DB::table('tasks')
            //     ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            //     ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            //     ->leftJoin('activities', 'activities.task_user_id', '=', 'task_user.id')
            //     ->select(
            //         'tasks.task_no',
            //         'tasks.id AS task_id',
            //         'tasks.task_name AS task_title',
            //         'tasks.task_description',
            //         'tasks.Time02 AS estimated_time',
            //         'task_user.id AS task_user_id',
            //         'task_user.due_date',
            //         'task_user.plan_date',
            //         'jobs.job_no',
            //         'jobs.contact_person',
            //         'jobs.customer_name',
            //         'jobs.id AS job_id',
            //         'jobs.contact_number',
            //         'jobs.job_description',
            //         'jobs.due_date AS job_due_date',
            //         DB::raw("IFNULL(activities.section_id, 1) AS current_section")
            //     )
            //     ->where(['tasks.id' => $task_id, 'task_user.qc_id' => 0])
            //     // ->groupBy('tasks.id')
            //     ->orderBy('task_user.created_at', 'DESC')
            //     ->take(1)
            //     ->get()[0];
            $details = DB::select("SELECT 
                `task_no`,
                CASE
                    WHEN BB IS NULL THEN 'N/A'
                    WHEN BB = '' THEN 'N/A'
                    ELSE BB
                END AS authorized_by,
                CASE
                    WHEN BB IS NULL THEN 0
                    WHEN BB = '' THEN 0
                    ELSE 1
                END  AS approved,
                CASE
                    WHEN CC > 0 THEN 0
                    ELSE 1
                END  AS fresh_task,
                `task_id`, 
                `task_title`, 
                `task_description`, 
                `estimated_time`, 
                `task_user_id`, 
                `due_date`, 
                `plan_date`, 
                `job_no`, 
                `contact_person`, 
                `customer_name`, 
                `job_id`, 
                `contact_number`, 
                `job_description`, 
                `job_due_date`, 
                current_section 
                FROM 
                (SELECT 
                `tasks`.`task_no`,
                (SELECT u.`name` FROM `task_authorizations` ta 
                INNER JOIN `users` u ON ta.authorized_by = u.id 
                WHERE ta.`task_user_id` = `task_user`.`id` AND DATE(ta.created_at) = DATE(CURDATE())) AS BB,
                (SELECT COUNT(id) FROM `activities` WHERE task_id=`tasks`.`id`) AS CC,
                `tasks`.`id` AS `task_id`, 
                `tasks`.`task_name` AS `task_title`, 
                `tasks`.`task_description`, 
                `tasks`.`Time02` AS `estimated_time`, 
                `task_user`.`id` AS `task_user_id`, 
                `task_user`.`due_date`, 
                `task_user`.`plan_date`, 
                `jobs`.`job_no`, 
                `jobs`.`contact_person`, 
                `jobs`.`customer_name`, 
                `jobs`.`id` AS `job_id`, 
                `jobs`.`contact_number`, 
                `jobs`.`job_description`, 
                `jobs`.`due_date` AS `job_due_date`, 
                IFNULL(activities.section_id, 1) AS current_section 
                FROM `tasks` 
                INNER JOIN `task_user` ON `tasks`.`id` = `task_user`.`task_id` 
                INNER JOIN `jobs` ON `jobs`.`id` = `tasks`.`job_id` 
                LEFT JOIN `activities` ON `activities`.`task_user_id` = `task_user`.`id` 
                WHERE (`tasks`.`id` = $task_id AND `task_user`.`qc_id` = 0) 
                ORDER BY `task_user`.`created_at` DESC 
                LIMIT 1) AS TBL")[0];
        } else {
            // $details = DB::table('tasks')
            //     ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            //     ->join('users', 'users.id', '=', 'task_user.qc_id')
            //     ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            //     ->leftJoin('activities', 'activities.task_user_id', '=', 'task_user.id')
            //     ->select(
            //         'tasks.task_no',
            //         'tasks.id AS task_id',
            //         'tasks.task_name AS task_title',
            //         'tasks.task_description',
            //         'tasks.Time02 AS estimated_time',
            //         'task_user.id AS task_user_id',
            //         'task_user.due_date',
            //         'task_user.plan_date',
            //         'users.name AS qc_name',
            //         'jobs.job_no',
            //         'jobs.contact_person',
            //         'jobs.customer_name',
            //         'jobs.id AS job_id',
            //         'jobs.contact_number',
            //         'jobs.job_description',
            //         'jobs.due_date AS job_due_date',
            //         DB::raw("IFNULL(activities.section_id, 1) AS current_section")
            //     )
            //     ->where('tasks.id', $task_id)
            //     ->groupBy('tasks.id')
            //     ->orderBy('task_user.id', 'DESC')
            //     ->take(1)
            //     ->get()[0];

            $details = DB::select("SELECT `task_no`,
                CASE
                    WHEN BB IS NULL THEN 'N/A'
                    WHEN BB = '' THEN 'N/A'
                    ELSE BB
                END AS authorized_by,
                CASE
                    WHEN BB IS NULL THEN 0
                    WHEN BB = '' THEN 0
                    ELSE 1
                END  AS approved,
                CASE
                    WHEN CC > 0 THEN 0
                    ELSE 1
                END  AS fresh_task,
                `task_id`, 
                `task_title`, 
                `task_description`, 
                `estimated_time`, 
                `task_user_id`, 
                `due_date`, 
                `plan_date`, 
                `qc_name`, 
                `job_no`, 
                `contact_person`, 
                `customer_name`, 
                `job_id`, 
                `contact_number`, 
                `job_description`, 
                `job_due_date`, 
                `current_section` FROM
                (SELECT 
                `tasks`.`task_no`,
                (SELECT u.`name` FROM `task_authorizations` ta 
                INNER JOIN `users` u ON ta.authorized_by = u.id 
                WHERE ta.`task_user_id` = `task_user`.`id` AND DATE(ta.created_at) = DATE(CURDATE())) AS BB,
                (SELECT COUNT(id) FROM `activities` WHERE task_id=`tasks`.`id`) AS CC,
                `tasks`.`id` AS `task_id`, 
                `tasks`.`task_name` AS `task_title`, 
                `tasks`.`task_description`, 
                `tasks`.`Time02` AS `estimated_time`, 
                `task_user`.`id` AS `task_user_id`, 
                `task_user`.`due_date`, 
                `task_user`.`plan_date`, 
                `users`.`name` AS `qc_name`, 
                `jobs`.`job_no`, 
                `jobs`.`contact_person`, 
                `jobs`.`customer_name`, 
                `jobs`.`id` AS `job_id`, 
                `jobs`.`contact_number`, 
                `jobs`.`job_description`, 
                `jobs`.`due_date` AS `job_due_date`, 
                IFNULL(activities.section_id, 1) AS current_section 
                FROM `tasks` 
                INNER JOIN `task_user` ON `tasks`.`id` = `task_user`.`task_id` 
                INNER JOIN `users` ON `users`.`id` = `task_user`.`qc_id` 
                INNER JOIN `jobs` ON `jobs`.`id` = `tasks`.`job_id` 
                LEFT JOIN `activities` ON `activities`.`task_user_id` = `task_user`.`id` 
                WHERE `tasks`.`id` = $task_id 
                -- GROUP BY `tasks`.`id` 
                ORDER BY `task_user`.`id` DESC 
                LIMIT 1) TBL")[0];
        }



        $due_date_change_history =  DB::table('job_due_date_changes')
            ->join('users', 'job_due_date_changes.changed_by', '=', 'users.id')
            ->select('job_due_date_changes.*', 'users.name')
            ->where('job_id', $details->job_id)
            ->orderBy('job_due_date_changes.id', 'DESC')
            ->get();

        $details->history = $due_date_change_history;
        $details->spent = $this->getSpentTime($task_id);
        return response()->json([
            'activitiesWithComments' => $activitiesWithComments,
            'details' => $details,
        ]);
    }

    public function getSpentTime($task_id)
    {
        $latest_ongoing = Activity::where(['task_id' => $task_id, 'section_id' => 3])->orderBy('id', 'DESC')->first();
        if (!$latest_ongoing) {
            return ['months' => 0, 'days' => sprintf("%02d", 0), 'hours' => sprintf("%02d", 0), 'minutes' => sprintf("%02d", 0)];
        }
        $all_ongoings = Activity::where(['task_id' => $task_id, 'section_id' => 3])->get();
        $months = 0;
        $days = 0;
        $hours = 0;
        $minutes = 0;
        foreach ($all_ongoings as $key => $ongoing) {
            $sdate = Carbon::parse($ongoing->created_at);
            $edate = Carbon::parse($ongoing->updated_at);
            $diff = $sdate->diff($edate);
            $months += $diff->m;
            $days += $diff->d;
            $hours += $diff->h;
            $minutes += $diff->i;
        }
        if ($latest_ongoing->created_at->eq($latest_ongoing->updated_at)) {
            $sdate2 = Carbon::parse($latest_ongoing->created_at);
            $edate2 = Carbon::parse(now());
            $diff2 = $sdate2->diff($edate2);
            $months += $diff2->m;
            $days += $diff2->d;
            $hours += $diff2->h;
            $minutes += $diff2->i;
        }

        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return ['months' => $months, 'days' => sprintf("%02d", $days), 'hours' => sprintf("%02d", $hours), 'minutes' => sprintf("%02d", $minutes)];
    }



    public function singleToday(Request $request)
    {
        $user_id = auth()->user()->id;
        // $task_id = $request->task_id;
        $activitiesWithComments = [];
        $activities = DB::select("SELECT
        `activities`.`id`, `jobs`.`job_no`, `sections`.`section_name`, `activities`.`user_id`, `activities`.`is_qc_task`,
        (SELECT `section_name` FROM `sections` WHERE `id` = `activities`.`prev_section_id`) AS prev_section,
        (SELECT `name` FROM `users` WHERE `id` = `activities`.`user_id`) AS NAME,
        `activities`.`created_at` AS startTime,
        `activities`.`updated_at` AS endTime,
        `employees`.`image`
        FROM
            `activities`
            INNER JOIN `tasks` ON (`activities`.`task_id` = `tasks`.`id`)
            INNER JOIN `sections` ON (`activities`.`section_id` = `sections`.`id`)
            INNER JOIN jobs ON (`tasks`.`job_id` = `jobs`.`id`)
            INNER JOIN users ON (`activities`.`user_id` = `users`.`id`)
            INNER JOIN employees ON (`users`.`employee_id` = `employees`.`id`)
            WHERE  `activities`.`qc_status` = 0
            AND activities.created_at BETWEEN DATE('2020-06-30') AND DATE('2020-07-01')
            ORDER BY `activities`.`id` DESC LIMIT 0, 100");

        foreach ($activities as $key => $activity) {
            $comments = DB::select("SELECT users.id AS user_id, users.name, comments.created_at, comments.activity_id, comments.comment, comments.comment_type, employees.image FROM comments
            INNER JOIN users ON (comments.user_id = users.id)
            INNER JOIN activities ON (comments.activity_id = activities.id)
            INNER JOIN employees ON (`users`.`employee_id` = `employees`.`id`)
            WHERE activities.id = " . $activity->id . "
            ORDER BY comments.id DESC");

            if (isset($comments)) {
                foreach ($comments as $key => $comment) {
                    array_push($activitiesWithComments, $comment);
                }
            }
            array_push($activitiesWithComments, $activity);
        }

        return $activitiesWithComments;
    }

    public function loadAllTasks()
    {
        $jobs = Job::with('tasks')->get();

        $data = [];
        foreach ($jobs as $key => $job) {
            $unassigned_tasks = $job->tasks()->where('isAssigned', 0)->orderBy('last_id')->get();
            $new_obj = [
                'id' => $job->id,
                'job_no' => $job->job_no,
                'tasks' => $unassigned_tasks,
            ];
            if (!$unassigned_tasks->isEmpty()) {
                array_push($data, $new_obj);
            }
        }
        return $data;
    }

    public function loadAllAssignedTasks()
    {
        $jobs = Job::with('tasks')->get();
        $data = [];
        $assigned_tasks = "";
        foreach ($jobs as $key => $job) {
            $assigned_tasks = $job->tasks()->where('isAssigned', 1)->get();
            foreach ($assigned_tasks as $key => $task) {
                $task->assign_data = DB::select("SELECT task_user.* FROM task_user WHERE status = 1 AND task_id = " . $task->id . " ORDER BY id DESC LIMIT 0,1");
            }
            $new_obj = [
                'id' => $job->id,
                'job_no' => $job->job_no,
                'tasks' => $assigned_tasks,
            ];
            if (!$assigned_tasks->isEmpty()) {
                array_push($data, $new_obj);
            }
        }
        return $data;
    }

    public function loadSubTasks($id)
    {
        return Task::find($id)->subtasks->load('user');
    }

    public function store(Request $request)
    {
        $tasks = $request->tasks;
        $type = $request->type;
        $user_id = auth()->user()->id;
        $task_assigned_employee = $request->task_assigned_employee;
        $task_assigned_qc_employee = $request->task_assigned_qc_employee;
        $task_priority = $request->task_priority;
        $task_due_date = date("Y-m-d", strtotime($request->task_due_date));
        $notification_type = "";

        $job = Task::find($tasks[0]['id'])->job;
        $job_due_date = date("Y-m-d", strtotime($job->due_date));
        $task_due_date = date("Y-m-d", strtotime($request->task_due_date));

        $task_due_date = Carbon::parse($task_due_date);
        $job_due_date = Carbon::parse($job_due_date);

        // check whether task's due date exceeding job's due date
        if (!$task_due_date->eq($job_due_date) && $task_due_date->greaterThan($job_due_date)) {
            return response()->json(['message' => 'Task due date cannot be exceeded job\'s due date'], 400);
        }

        // this is for task re-assigning
        if ($type == 2) {
            return $this->taskReassigning($tasks, $user_id, $task_assigned_employee, $task_assigned_qc_employee, $task_priority, $task_due_date);
        }

        DB::beginTransaction();

        $job_id = Task::find($tasks[0]['id'])->job->id;

        // add assigned task's job id and user
        $job_user = DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();

        if (!isset($job_user)) {
            $new_job_user = DB::table('job_user')->insertGetId(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'created_at' => now(), 'updated_at' => now(), 'jobtype_id' => 1]);
            $notification_type = "New job";
        } else {
            $new_job_user = $job_user->id;
            DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->update(['updated_at' => now()]);
            $notification_type = "New task has been assigned";
        }

        $job_user = DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();

        try {
            foreach ($tasks as $key => $task) {
                // update task status as assigned (1)
                Task::find($task['id'])->update(['isAssigned' => 1]);

                $new_task_user = DB::table('task_user')->insertGetId([
                    'job_user_id' => $job_user->id,
                    'user_id' => $task_assigned_employee,
                    'task_id' => $task['id'],
                    'qc_id' => $task_assigned_qc_employee,
                    'priority' => $task_priority,
                    'assign_date' => now(),
                    'assign_by' => $user_id,
                    'due_date' => $task_due_date,
                    'plan_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $job = Job::find($job_id);
                $notification = Notification::create([
                    'user_id' => $task_assigned_employee,
                    'job_user_id' => $new_job_user,
                    'task_user_id' => $new_task_user,
                    'job_id' => $job_id,
                    'assigend_by' => $user_id,
                    'notification_type' => 1,
                    'title' => $notification_type,
                    'count' => 10,
                    'description' => $job->job,
                    'status' => 1,
                ]);
            }

            $isAllTaskAssigned = Job::find($job_id)->tasks()->where(['isAssigned' => 0, ['Time01', '!=', 0]])->get();
            if ($isAllTaskAssigned->isEmpty()) {
                Job::find($job_id)->update(['status' => 3]);
            }

            DB::commit();
            return response()->json(['message' => 'Success', 'assigned_emp' => $task_assigned_employee, 'notification_id' => $notification->id], 201);
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json(['message' => $th], 400);
        }
    }

    public function taskReassigning($tasks, $user_id, $task_assigned_employee, $task_assigned_qc_employee, $task_priority, $task_due_date)
    {
        $notification_type = "";
        DB::beginTransaction();
        try {
            foreach ($tasks as $key => $task) {
                $check = DB::table('task_user')->where(['user_id' => $task_assigned_employee, 'task_id' => $task['id'], 'status' => 1])->get();
                if (!$check->isEmpty()) {
                    return response()->json(['message' => 'This task has been already assigned to this user'], 400);
                }
            }

            foreach ($tasks as $key => $task) {
                $t_users = DB::table('task_user')->where(['task_id' => $task['id'], 'status' => 1, ['qc_id', '!=', 0]])->get();

                DB::table('task_user')->where(['task_id' => $task['id'], 'status' => 1, ['qc_id', '!=', 0]])->update(['status' => 7]);
                DB::table('task_user')->where(['task_id' => $task['id'], 'status' => 1, 'qc_id' => 0])->update(['status' => 0]);
            }


            $job_id = Task::find($task['id'])->job->id;


            if (!$t_users->isEmpty()) {
                $juid = $t_users[0]->job_user_id;
                $ss = DB::table('task_user')->where(['job_user_id' => $juid, 'status' => 1, ['qc_id', '!=', 0]])->get();
                if ($ss->isEmpty()) {
                    DB::table('job_user')->where('id', $juid)->update(['status' => 6]);
                }
            }


            $job_user = DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();

            if (!isset($job_user)) {
                $new_job_user = DB::table('job_user')->insertGetId(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'created_at' => now(), 'updated_at' => now(), 'jobtype_id' => 1]);
                $notification_type = "New job";
            } else {
                $new_job_user = $job_user->id;
                DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->update(['updated_at' => now()]);
                $notification_type = "New task has been assigned";
            }

            $job_user = DB::table('job_user')->where(['user_id' => $task_assigned_employee, 'job_id' => $job_id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();

            foreach ($tasks as $key => $task) {

                $new_task_user = DB::table('task_user')->insertGetId([
                    'job_user_id' => $job_user->id,
                    'user_id' => $task_assigned_employee,
                    'task_id' => $task['id'],
                    'qc_id' => $task_assigned_qc_employee,
                    'priority' => $task_priority,
                    'assign_date' => now(),
                    'assign_by' => $user_id,
                    'due_date' => $task_due_date,
                    'plan_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $job = Job::find($job_id);
                $notification = Notification::create([
                    'user_id' => $task_assigned_employee,
                    'job_user_id' => $new_job_user,
                    'task_user_id' => $new_task_user,
                    'job_id' => $job_id,
                    'assigend_by' => $user_id,
                    'notification_type' => 1,
                    'title' => $notification_type,
                    'count' => 10,
                    'description' => $job->job,
                    'status' => 1,
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'Success', 'assigned_emp' => $task_assigned_employee, 'notification_id' => $notification->id], 201);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
    }

    public function saveSubTask(Request $request)
    {
        $task_id = $request->task_id;
        $task_no = Task::find($task_id)->task_no;
        $st_name = $request->stn;
        $st_employee = $request->ste;
        $st_due_date = $request->stdd;

        $sub_task_count = DB::table('sub_tasks')->where('task_id', $task_id)->count();
        $sub_task_no = $task_no . '.' . ++$sub_task_count;
        $saved_sub_task = SubTasks::create([
            'task_id' => $task_id,
            'sub_task_no' => $sub_task_no,
            'user_id' => $st_employee,
            'sub_task' => $st_name,
            'due_date' => date("Y-m-d", strtotime($st_due_date)),
            'assigned_by' => auth()->user()->id,
        ]);

        return response()->json(['message' => 'success', 'sub_task' => $saved_sub_task->load('user')], 201);
    }

    public function savePlanDate(Request $request)
    {
        $task_user_id = $request->task_user_id;

        $user_task = DB::table('task_user')->where(['id' => $task_user_id])->get();

        $due_date = date("Y-m-d", strtotime($user_task->first()->due_date));
        $plan_date = date("Y-m-d", strtotime($request->plan_date));

        $due_date = Carbon::parse($due_date);
        $plan_date = Carbon::parse($plan_date);

        if (!$plan_date->eq($due_date) && $plan_date->greaterThan($due_date)) {
            return response()->json(['message' => 'Plan date cannot be exceeded task due date'], 400);
        }

        $plan_date = date("Y-m-d", strtotime($request->plan_date));

        $user_task = DB::table('task_user')->where(['id' => $task_user_id])->update(['plan_date' => $plan_date]);

        return response()->json(['message' => 'success'], 201);
    }

    public function loadJobTasks($id)
    {
        return Task::where(['job_id' => $id, 'isAssigned' => 0])->orderBy('id', 'DESC')->get();
    }

    public function loadJobTasksForUser($id, $user)
    {
        $job = Job::find($id);
        $job_user = DB::table('job_user')->where(['user_id' => $user, 'job_id' => $job->id, 'status' => 1, 'jobtype_id' => 1])->orderBy('created_at', 'DESC')->first();
        $a_tasks = DB::table('task_user')->where(['job_user_id' => $job_user->id, ['status', '!=', 0], ['qc_id', '!=', 0]])->orderBy('created_at', 'DESC')->get();
        $tasks = [];
        foreach ($a_tasks as $key => $a_task) {
            $task = Task::find($a_task->task_id);
            array_push($tasks, $task);
        }
        return $tasks;
    }

    public function changeTaskDueDate(Request $request)
    {
        $new_due_date = $request->new_due_date;
        $task_user_id = $request->task_user_id;

        $data = DB::table('task_user')
            ->join('job_user', 'task_user.job_user_id', '=', 'job_user.id')
            ->join('jobs', 'job_user.job_id', '=', 'jobs.id')
            ->select('job_user.job_id', 'jobs.due_date')
            ->where(['task_user.id' => $task_user_id])
            ->get();

        $job_due_date = date("Y-m-d", strtotime($data[0]->due_date));
        $new_due_date = date("Y-m-d", strtotime($new_due_date));

        $job_due_date = Carbon::parse($job_due_date);
        $new_due_date = Carbon::parse($new_due_date);

        if (!$new_due_date->eq($job_due_date) && $new_due_date->greaterThan($job_due_date)) {
            return response()->json(['message' => 'Task due date cannot be exceeded job\'s due date'], 400);
        }
        $task_user = DB::table('task_user')->where(['id' => $task_user_id])->update(['due_date' => $new_due_date]);
        return response()->json(['ss' => 'success']);
    }

    public function changeEstimateTime(Request $request)
    {
        $task_id = $request->task_id;
        $last_activity_id = $request->last_activity_id;
        $estimate_time = $request->estimate_time;
        $reason = $request->reason;
        $reasonWithTime = $request->reasonWithTime;
        $user_id = auth()->user()->id;

        $task = Task::find($task_id);

        $initial_record = DB::table('estimate_time_changes')->where(['task_id' => $task_id, 'changed_by' => 0])
            ->orderBy('id', 'DESC')->first();

        if (!$initial_record) {
            DB::table('estimate_time_changes')->insert([
                'task_id' => $task_id,
                'changed_by' => 0,
                'estimate_time' => $task['Time02'],
                'reason' => 'Initially Allocated Time',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('estimate_time_changes')->insert([
            'task_id' => $task_id,
            'changed_by' => $user_id,
            'estimate_time' => $estimate_time,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $comment = Comment::create([
            'activity_id' => $last_activity_id,
            'user_id' => $user_id,
            'comment' => 'Estimate time changed - ' . $reasonWithTime,
            'comment_type' => 12,
        ]);

        $task->update(['Time02' => $estimate_time]);
        $employee_id = User::find($comment->user_id)->employee_id;
        $comment->image = Employee::find($employee_id)->image;

        return response()->json(['message' => 'success', 'comment' => $comment]);
    }

    public function loadTasksForUser($user)
    {
        $activity_plan = ActivityPlan::where('user_id', $user)->get();
        $user = User::find($user);
        return [
            "tasks" => $user->plannedTasks,
            "activityPlan" => $activity_plan,
        ];
    }

    public function updateTaskDates(Request $task)
    {
        if (auth()->user()->id != $task->user_id) {
            return response()->json(['code' => 500, 'message' => "You are not allowed to change other employees plan!"], 500);
        }

        try {
            $user_task = DB::table('task_user')->where(['user_id' => $task->user_id, 'id' => $task->id])->get(); //to get the due date of the task
            // $user_task = DB::table('task_user')->where(['user_id' => $task->user_id, 'task_id' => $task->task_id])->get();

            $due_date = date("Y-m-d", strtotime($user_task->first()->due_date));
            $plan_date = date("Y-m-d", strtotime($task->plan_date));
            $end_date = date("Y-m-d", strtotime($task->end_date));

            $due_date = Carbon::parse($due_date);
            $due_date1 = Carbon::parse($due_date)->addDays(1);
            $plan_date = Carbon::parse($plan_date);
            $end_date = Carbon::parse($end_date);

            if ($plan_date->greaterThan($due_date)) {
                return response()->json(['code' => 400, 'message' => 'Plan date cannot be exceeded task due date (' . $due_date . ')'], 400);
            }
            if ($end_date->greaterThan($due_date1)) {
                return response()->json(['code' => 400, 'message' => 'End date cannot be exceeded task due date (' . $due_date . ')'], 400);
            }

            if ($task->all_day) {
                $plan_date = date("Y-m-d", strtotime($task->plan_date));
                $end_date = date("Y-m-d", strtotime($task->end_date));
                $to = date_format(date_time_set(Carbon::parse(date("Y-m-d", strtotime($plan_date))), 23, 59), 'Y-m-d H:i:s'); //don't touch this it works

                $user_task_obj = DB::table('task_user') //getting the current assigned task for selected Date
                    ->where(['user_id' => $task->user_id])
                    ->whereBetween('plan_date', [$task->plan_date, $to])
                    ->orderBy('end_date', 'DESC')->get();

                if (count($user_task_obj) > 0) {
                    if (!$user_task_obj[0]->end_date) {
                        $plan_date = date_format(date_time_set(Carbon::parse(date("Y-m-d", strtotime($plan_date))), 8, 30), 'Y-m-d H:i:s');
                    } else {
                        $plan_date = date('Y-m-d H:i:s', strtotime($user_task_obj[0]->end_date));
                    }
                } else {
                    $plan_date = date_format(date_time_set(Carbon::parse(date("Y-m-d", strtotime($plan_date))), 8, 30), 'Y-m-d H:i:s');
                }
                $end_date = new \DateTime($plan_date);
                $end_date->add(new \DateInterval('PT' . $task->time * 60 . 'M'));
                $end_date->format('Y-m-d H:i:s');
                // $end_date = Carbon::parse($end_date));

            } else {
                $plan_date = date('Y-m-d H:i:s', strtotime($task->plan_date));
                $end_date = date('Y-m-d H:i:s', strtotime($task->end_date));
            }

            $user_task = DB::table('task_user')
                ->where(['user_id' => $task->user_id, 'id' => $task->id])
                ->update(['plan_date' => $plan_date, 'end_date' => $end_date, 'all_day' => 0]);
            return response()->json([
                'message' => 'success',
                "task" => [
                    "plan_date" => $plan_date,
                    "end_date" => $end_date,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'message' => $e], 500);
        }
    }

    public function sendApproveRequest(Request $request)
    {
        $this->validate($request, [
            'request_to' => 'required'
        ]);

        $task_user = DB::table('task_user')->where('id', $request->task_user_id)->orderBy('id', 'DESC')->first();

        AuthorizationRequest::create([
            'job_id' => $request->job_id,
            'request_to' => $request->request_to,
            'request_by' => $request->request_by,
            'task_user_id' => $request->task_user_id,
            'job_user_id' => $task_user->job_user_id,
            'status' => 15,
        ]);
        return $request;
    }

    public function approveTasks(Request $request)
    {

        $user_id = auth()->user()->id;
        $task_user_id = $request->task_user_id;

        DB::beginTransaction();

        try {
            $task_user = DB::table('task_user')->where(['id' => $task_user_id])->latest()->first();
            $entire_job = 0;
            if ($request->allTasks) {
                $entire_job = 1;
                AuthorizationRequest::where(['job_user_id' => $task_user->job_user_id, 'request_to' => $user_id, 'status' => 15])
                    ->update(['status' => 16, 'all_tasks' => $entire_job]);
            } else {
                AuthorizationRequest::where(['task_user_id' => $task_user_id, 'request_to' => $user_id, 'status' => 15])
                    ->update(['status' => 16, 'all_tasks' => $entire_job]);
            }

            TaskAuthorization::create([
                'job_user_id' => $task_user->job_user_id,
                'task_user_id' => $task_user_id,
                'authorized_by' => $user_id,
                'entire_job' => $entire_job,
            ]);

            AuthorizationRequest::where(['task_user_id' => $task_user_id, 'request_to' => $user_id, 'status' => 15])
                ->update(['status' => 16, 'all_tasks' => $entire_job]);

            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
        return $request;
    }

    public function updateQcEmployee(Request $request)
    {
        $task_id = $request->task_id;
        $job_id = $request->job_id;
        $employee_id = $request->employee_id;

        DB::beginTransaction();

        try {
            // $task_user = DB::table('task_user')->where(['task_id' => $task_id, ['qc_id', '!=', 0], 'status' => 1])->orderBy('id', 'DESC')->first();

            $task_user_qc = DB::table('task_user')->where(['task_id' => $task_id, 'qc_id' => 0, 'status' => 1])->orderBy('id', 'DESC')->first();
            if ($task_user_qc) {
                $qc_job_user = DB::table('task_user')->where(['job_user_id' => $task_user_qc->job_user_id])->get();
                if (count($qc_job_user) == 1) {
                    $qc_job_user->update(['status' => 0]);
                }
            }
            DB::table('task_user')->where(['task_id' => $task_id, ['qc_id', '!=', 0], 'status' => 1])->update(['qc_id' => $employee_id]);
            DB::table('task_user')->where(['task_id' => $task_id, 'qc_id' => 0, 'status' => 1])->update(['status' => 0]);

            // $new_job_user_id = DB::table('job_user')->insertGetId(['user_id' => $employee_id, 'job_id' => $job_id, 'created_at' => now(), 'updated_at' => now(), 'jobtype_id' => 2]);

            // $new_task_user = DB::table('task_user')->insertGetId([
            //     'job_user_id' => $new_job_user_id,
            //     'user_id' => $employee_id,
            //     'task_id' => $task_id,
            //     'qc_id' => 0,
            //     'priority' => 1,
            //     'assign_date' => now(),
            //     'assign_by' => auth()->user()->id,
            //     'due_date' => $task_user->due_date,
            //     'plan_date' => null,
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);

            // $job = Job::find($job_id);
            // $notification = Notification::create([
            //     'user_id' => $employee_id,
            //     'job_user_id' => $new_job_user_id,
            //     'task_user_id' => $new_task_user,
            //     'job_id' => $job_id,
            //     'assigend_by' => auth()->user()->id,
            //     'notification_type' => 1,
            //     'title' => "New task has been assigned",
            //     'count' => 10,
            //     'description' => $job->job,
            //     'status' => 1,
            // ]);
            DB::commit();
            return response()->json(['message' => 'success'], 201);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
    }
}
