<?php

namespace App\Http\Controllers;

use App\Activity;
use App\Comment;
use App\Employee;
use App\Job;
use App\Task;
use App\TaskHoldTemp;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function ongoingJobs(Request $request)
    {
        $updated_user = $request->user;
        $division_id = $request->division != 0 ? $request->division : auth()->user()->division_id;
        if ($updated_user != 0) {
            $users = User::select(['id', 'name', 'employee_id'])->where(['division_id' => $division_id, 'status' => 1, 'id' => $updated_user])->get();
        } else {
            $users = User::select(['id', 'name', 'employee_id'])->where(['division_id' => $division_id, 'user_type' => 4, 'status' => 1])->get();
        }
        $data = [];
        $hasOngoing = false;
        foreach ($users as $key => $user) {
            // $user_copy = clone $user;
            $emp = Employee::find($user->employee_id);
            $user->image = null;
            if (isset($emp)) {
                $user->image = $emp->image;
            }
            $user->spent = [];
            $user->ongoing = [];
            $user->ongoing_job = [];
            $user->task_user = [];

            $user->pending_tasks = DB::select("SELECT
                            `jobs`.`job_no`,
                            `task_user`.`task_id`,
                            `tasks`.`task_no`,
                            `tasks`.`Time01`,
                            `tasks`.`Time02`,
                            `tasks`.`Time03`,
                            MAX(`task_user`.`due_date`) AS due_date
                        FROM
                            `task_user`
                            INNER JOIN `tasks`
                            ON `task_user`.`task_id` = `tasks`.`id`
                            INNER JOIN `jobs`
                            ON `tasks`.`job_id` = `jobs`.`id`
                        WHERE `task_user`.`user_id` = " . $user->id . "
                        GROUP BY `task_user`.`task_id`
                        ORDER BY due_date ASC LIMIT 0,5");

            $tasksuser = DB::table('task_user')->where(['user_id' => $user->id, 'status' => 1])->get();
            if (!$tasksuser->isEmpty()) {
                $hasOngoing = false;
                // $latest_ongoing = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])
                //     ->where(['user_id' => $user->id])->orderBy('id', 'DESC')->first();

                $latest_ongoing = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])
                    ->where(['user_id' => $user->id, 'section_id' => 3])->orderBy('id', 'DESC')->first();
                if ($latest_ongoing) {
                    $latest_ongoing_check = Activity::where(['task_user_id' => $latest_ongoing->task_user_id])->orderBy('id', 'DESC')->first();
                    $user->fuck = $latest_ongoing->id . '/' . $latest_ongoing_check->id;
                    // if ($latest_ongoing && $latest_ongoing->section_id == 3) {
                    if ($latest_ongoing->id == $latest_ongoing_check->id) {
                        $hasOngoing = true;
                        $all_ongoings = Activity::where(['user_id' => $user->id, 'task_id' => $latest_ongoing->task_id, 'section_id' => 3])->get();
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
                        $sdate2 = Carbon::parse($latest_ongoing->created_at);
                        $edate2 = Carbon::parse(now());
                        $diff2 = $sdate2->diff($edate2);
                        $months += $diff2->m;
                        $days += $diff2->d;
                        $hours += $diff2->h;
                        $minutes += $diff2->i;


                        $spent = $this->getSpentTime($months, $days, $hours, $minutes);

                        $user->spent = $spent;
                        $user->ongoing = $latest_ongoing;
                        // $latest_ongoing->task = Task::find($latest_ongoing->task_id);
                        $latest_ongoing->task = Task::select('id', 'task_no', 'Time02', 'job_id')->where('id', $latest_ongoing->task_id)->first();
                        $abc = Task::where(['job_id' => Task::find($latest_ongoing->task_id)->job->id, 'Time01' => 0])->get();
                        if (!$abc->isEmpty()) {
                            $latest_ongoing->task->task_title = $abc[0]->task_name;
                        } else {
                            $latest_ongoing->task->task_title = Task::find($latest_ongoing->task_id)->task_name;
                        }
                        $user->holdAction = null;
                        $user->isHolding = false;
                        $user->ongoing_job = $latest_ongoing->task->job->only(['id', 'job_no', 'customer_name', 'job_description']);
                        $user->task_user = DB::table('task_user')->select('due_date', 'plan_date', 'assign_date')->where(['id' => $latest_ongoing->task_user_id])->get()->last();
                        $user->pending_tasks = DB::select("SELECT
                                `jobs`.`job_no`,
                                `task_user`.`task_id`,
                                `tasks`.`task_no`,
                                `tasks`.`Time01`,
                                `tasks`.`Time02`,
                                `tasks`.`Time03`,
                                MAX(`task_user`.`due_date`) AS due_date
                            FROM
                                `task_user`
                                INNER JOIN `tasks`
                                ON `task_user`.`task_id` = `tasks`.`id`
                                INNER JOIN `jobs`
                                ON `tasks`.`job_id` = `jobs`.`id`
                            WHERE `task_user`.`user_id` = " . $user->id . "
                            AND `task_user`.`id` != " . $latest_ongoing->task_user_id . "
                            GROUP BY `task_user`.`task_id`
                            ORDER BY due_date ASC LIMIT 0,5");
                        // if ($hasOngoing) {
                        //     break;
                        // }
                    }
                }

                if (!$hasOngoing) {
                    $taskHoldAction = TaskHoldTemp::where('user_id', $user->id)->orderBy('created_at', 'DESC')->first();
                    if (isset($taskHoldAction)) {
                        $latest_ongoing = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $taskHoldAction->task_user_id])->orderBy('id', 'DESC')->first();
                        $latest_ongoing->task = Task::find($latest_ongoing->task_id);
                        $user->holdAction = $taskHoldAction;
                        $months = 0;
                        $days = 0;
                        $hours = 0;
                        $minutes = 0;
                        $sdate2 = Carbon::parse($taskHoldAction->created_at);
                        $edate2 = Carbon::parse(now());
                        $diff2 = $sdate2->diff($edate2);
                        $months += $diff2->m;
                        $days += $diff2->d;
                        $hours += $diff2->h;
                        $minutes += $diff2->i;

                        $spent = $this->getSpentTime($months, $days, $hours, $minutes);

                        $user->spent = $spent;
                        $user->ongoing = $latest_ongoing;
                        $user->isHolding = true;
                    } else {
                        $latest_ongoing = Activity::where(['user_id' => $user->id, 'section_id' => 3])->orderBy('id', 'DESC')->first();
                        $comment = Comment::where(['user_id' => $user->id])->orderBy('created_at', 'DESC')->first();
                        if ($comment && $comment->hasOngoingTask == 0) {
                            $latest_ongoing_comment = Activity::where(['user_id' => $user->id, 'id' => $comment->activity_id])->first();

                            $isOngoingTaskAvailable = false;

                            foreach ($tasksuser as $key => $t_user) {
                                $user_activity = Activity::where(['task_user_id' => $t_user->id])->orderBy('id', 'DESC')->first();
                                if ($user_activity && $user_activity->section_id == 3) {
                                    $isOngoingTaskAvailable = true;
                                }
                            }

                            if (!$isOngoingTaskAvailable && $latest_ongoing && $latest_ongoing_comment && $latest_ongoing->id <= $latest_ongoing_comment->id) {
                                $latest_ongoing_comment->task = Task::find($latest_ongoing_comment->task_id);
                                $user->holdAction = ['type' => $comment->comment_type];
                                $months = 0;
                                $days = 0;
                                $hours = 0;
                                $minutes = 0;
                                $sdate2 = Carbon::parse($latest_ongoing_comment->updated_at);
                                $edate2 = Carbon::parse(now());
                                $diff2 = $sdate2->diff($edate2);
                                $months += $diff2->m;
                                $days += $diff2->d;
                                $hours += $diff2->h;
                                $minutes += $diff2->i;

                                $spent = $this->getSpentTime($months, $days, $hours, $minutes);

                                $user->spent = $spent;
                                $user->ongoing = $latest_ongoing_comment;
                                $user->isHolding = true;
                            }
                        } else {
                            $user->ssssssssssssssss = 'awa';
                        }
                    }
                }
            }
            array_push($data, $user);
        }
        return $data;
    }

    public function getSpentTime($months, $days, $hours, $minutes)
    {
        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return ['months' => $months, 'days' => sprintf("%02d", $days), 'hours' => sprintf("%02d", $hours), 'minutes' => sprintf("%02d", $minutes)];
    }

    public function summary(Request $request)
    {
        $emp_user_id = $request->emp_user_id['id'];
        $emp_user = User::find($emp_user_id);
        $user_id = auth()->user()->id;
        $jobs = User::find($emp_user_id)->jobsActive(1)->orderBy('due_date', 'ASC')->get();
        $qc_jobs = User::find($emp_user_id)->jobsActive(2)->orderBy('due_date', 'ASC')->get();
        // $jobs = User::find($emp_user_id)->jobsActiveAll()->orderBy('due_date', 'ASC')->get();
        $assigned_jobs = [];
        $f_Activity = null;
        // return $qc_jobs;
        foreach ($jobs as $key => $job) {
            if (!$job->customer_name) {
                $job->customer_name = 'Quick Job';
            }
            $a_jobs = DB::table('job_user')->where(['user_id' => $emp_user_id, 'job_id' => $job->id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            if (isset($a_jobs)) {
                $job->job_user = $a_jobs;
                array_push($assigned_jobs, $job);
            }
        }
        foreach ($qc_jobs as $key => $job) {
            $a_jobs = DB::table('job_user')->where(['user_id' => $emp_user_id, 'job_id' => $job->id, 'jobtype_id' => 2, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            if (isset($a_jobs)) {
                $job->job_user = $a_jobs;
                array_push($assigned_jobs, $job);
            }
        }
        // return $assigned_jobs;
        foreach ($assigned_jobs as $key => $job) {
            $isOngoing = false;
            // return response()->json(['fffffffffffffffff' => $job]);
            // $no_tasks = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, ['status', '!=', 0], ['qc_id', '!=', 0]])->count('id');
            // $finished_tasks = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, 'status' => 6, ['qc_id', '!=', 0]])->count('id');
            $no_tasks = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, ['status', '!=', 0]])->count('id');
            $finished_tasks = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, 'status' => 6])->count('id');

            // $tasks_user = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, ['status', '!=', 0], ['qc_id', '!=', 0]])->get();
            $tasks_user = DB::table('task_user')->where(['job_user_id' => $job->job_user->id, ['status', '!=', 0]])->get();
            $job->tasks = $tasks_user;
            $qc_tasks = 0;
            if (!$tasks_user->isEmpty()) {
                foreach ($tasks_user as $key => $task) {
                    $ongoing_activity = Activity::where(['task_user_id' => $task->id, 'qc_status' => 0])->orderBy('id', 'DESC')->first();
                    if (isset($ongoing_activity) && $ongoing_activity->section_id == 3) {
                        $isOngoing = true;
                        break;
                    }
                }

                foreach ($tasks_user as $key => $task) {
                    $first_activity = Activity::where(['task_user_id' => $tasks_user[$key]->id, 'qc_status' => 0, 'section_id' => 3])->orderBy('id', 'ASC')->first();
                    if (isset($first_activity)) {
                        $f_Activity = $first_activity->created_at;
                        break;
                    }
                }

                foreach ($tasks_user as $key => $task) {
                    $last_activity = Activity::where(['task_user_id' => $task->id, 'qc_status' => 0])->orderBy('id', 'DESC')->first();
                    $job->plan_date = $task->plan_date;
                    if (isset($last_activity)) {
                        if ($last_activity->section_id == 5) {
                            $qc_tasks += 1;
                        }
                    }
                }
            }
            $job->ongoing = $isOngoing;
            $job->no_of_tasks = $no_tasks;
            $job->no_of_finished_tasks = $finished_tasks;
            $job->no_of_qc_tasks = $qc_tasks;
            $remainings = (int) $no_tasks - (int) $finished_tasks;
            $job->no_of_remaining_tasks = $remainings;
            $job->job_status = $remainings == 0 ? 'Finished' : 'Started';
            if ($isOngoing) {
                $job->job_status = "Ongoing";
            }
            if (!$f_Activity) {
                $job->job_status = "Not Started";
            }

            $job->first_activity = $f_Activity;
            // $job->last_activity = $last_activity->updated_at;
            $job->last_activity = null;
            $job->is_qc_job = $job->job_user->jobtype_id == 2 ? true : false;
        }
        $sorted = collect($assigned_jobs)->sortByDesc('first_activity')->values()->all();
        return $sorted;
    }

    public function tasks_summary(Request $request)
    {
        $job_user_id = $request->job_user_id;
        $a_jobs = DB::table('task_user')
            ->join('tasks', 'task_user.task_id', '=', 'tasks.id')
            ->select('tasks.*', 'task_user.*')
            ->where(['job_user_id' => $job_user_id, ['status', '!=', 0]])->get();
        foreach ($a_jobs as $key => $t_user) {
            if ($t_user->qc_id != 0) {
                $qc_user = User::find($t_user->qc_id);
            }
            $latest_ongoing = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id])->orderBy('id', 'DESC')->first();
            $finished_activity = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id, 'section_id' => 6])->orderBy('id', 'DESC')->first();
            $started_activity = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id, 'section_id' => 3])->orderBy('created_at', 'ASC')->first();
            $all_ongoings = Activity::where(['task_user_id' => $t_user->id, 'section_id' => 3])->get();
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

            if ($latest_ongoing && $latest_ongoing->section_id == 3) {
                $sdate2 = Carbon::parse($latest_ongoing->created_at);
                $edate2 = Carbon::parse(now());
                $diff2 = $sdate2->diff($edate2);
                $months += $diff2->m;
                $days += $diff2->d;
                $hours += $diff2->h;
                $minutes += $diff2->i;
            }
            $task_status = 'Back Log';
            if ($latest_ongoing) {
                switch ($latest_ongoing->section_id) {
                    case 1:
                        $task_status = "Back Log";
                        break;
                    case 2:
                        $task_status = "Picked";
                        break;
                    case 3:
                        $task_status = "Ongoing";
                        break;
                    case 4:
                        $task_status = "Hold";
                        break;
                    case 5:
                        $task_status = "QC (" . explode(" ", $qc_user->name)[0] . ")";
                        break;
                    case 6:
                        $task_status = "Finished";
                        break;

                    default:
                        $task_status = "Back Log";
                        break;
                }
            }
            // $task_status = $t_user->status == 6 ? 'Finished' : 'Ongoing';
            $spent = $this->getSpentTime($months, $days, $hours, $minutes);
            $t_user->spent = $spent;
            // if ($days == 0 && $hours == 0 && $minutes == 0) {
            //   $task_status = "Not Started";
            // }
            $t_user->task_status = $task_status;
            $f_at = null;
            $s_at = null;
            if (isset($finished_activity)) {
                $f_at = $finished_activity->created_at;
            }
            if (isset($started_activity)) {
                $s_at = $started_activity->created_at;
            }
            $t_user->finished_at = $f_at;
            $t_user->started_at = $s_at;
        }
        // $sorted = collect($a_jobs);
        // $sorted->sortBy('id');
        // return $a_jobs;
        $sorted = $a_jobs->sortByDesc('started_at')->values()->all();
        // $sorted = $a_jobs->sortByDesc(function($col) {
        //   return $col->id;
        // })->values()->all();
        return $sorted;
    }

    public function mobileAppOngoing()
    {
        return DB::select("SELECT
        CONCAT(`users`.`name`,'\n',`sections`.`section_name`) as NAME,
          `jobs`.`job_no`,
          `tasks`.`task_no`,
          `jobs`.`job_description`,
          `task_user`.`due_date`,
          `tasks`.`task_name`,
          `tasks`.`task_description`,
          `tasks`.`Time01`,
          `tasks`.`Time02`,
          `tasks`.`Time03`,
          `sections`.`section_name`
        FROM
          `job_manager`.`jobs`
          INNER JOIN `job_manager`.`tasks`
            ON(`jobs`.`id` = `tasks`.`job_id`)
          INNER JOIN `job_manager`.`task_user`
            ON(
              `tasks`.`id` = `task_user`.`task_id`
            )
          INNER JOIN `job_manager`.`users`
            ON(
              `task_user`.`user_id` = `users`.`id`
            )
          INNER JOIN `job_manager`.`sections`
            ON(
              `sections`.`id` = `tasks`.`current_status`
            )
            order by  `task_user`.`due_date`,`users`.`name`

        ");
    }

    public function dashboardTaskChange(Request $request)
    {

        $user_id = auth()->user()->id;
        $type = (int) $request->payload['type'];
        $comment = $request->payload['comment'];
        $ongoing_activity = "";
        $comment_title = "";
        $c_type = 0;
        $tasksuser = DB::table('task_user')->where(['user_id' => $user_id, 'status' => 1])->get();
        if (!$tasksuser->isEmpty()) {
            foreach ($tasksuser as $key => $t_user) {
                $latest_ongoing = Activity::where(['task_user_id' => $t_user->id])->orderBy('id', 'DESC')->first();
                if ($latest_ongoing && $latest_ongoing->section_id == 3) {
                    $ongoing_activity = $latest_ongoing;
                }
            }
        }

        switch ($type) {
            case 1:
                $comment_title = "Meeting";
                $c_type = 6;
                break;
            case 2:
                $comment_title = "Lunch";
                $c_type = 7;
                break;
            case 3:
                $comment_title = "Hold";
                $c_type = 8;
                break;
            case 4:
                $comment_title = "Day Off";
                $c_type = 9;
                break;
            case 5:
                $comment_title = "Help";
                $c_type = 10;
                break;
            case 6:
                $comment_title = "Leave";
                $c_type = 11;
                break;
            default:

                break;
        }
        if (isset($comment)) {
            $comment_title = $comment_title . ' - ' . $comment;
        }

        DB::beginTransaction();

        try {

            // if ((int) $type == 4) {
            $last_comment = Comment::where(['user_id' => $user_id, ['comment_type', '!=', 9], ['comment_type', '!=', 1]])->orderBy('created_at', 'DESC')->first();
            if ($last_comment && $last_comment->isFinished == 0) {
                $last_comment->fresh()->update(['updated_at' => now(), 'isFinished' => 1]);
            }
            // }

            if ($ongoing_activity != "") {
                TaskHoldTemp::create([
                    'task_user_id' => $ongoing_activity->task_user_id,
                    'user_id' => $user_id,
                    'type' => $c_type,
                ]);

                $ongoing_activity->update(['updated_at' => now()]);

                $saved_activity = Activity::create([
                    'task_user_id' => $ongoing_activity->task_user_id,
                    'task_id' => $ongoing_activity->task_id,
                    'user_id' => $user_id,
                    'prev_section_id' => 3,
                    'section_id' => 4,
                    'qc_status' => 0,
                    'is_qc_task' => $ongoing_activity->is_qc_task,
                ]);

                // make an comment
                Comment::create([
                    'activity_id' => $saved_activity->id,
                    'user_id' => $user_id,
                    'comment' => $comment_title,
                    'comment_type' => $c_type,
                ]);
            } else {

                $last_ongoing = Activity::where(['user_id' => $user_id])->orderBy('id', 'DESC')->first();

                $t_hold_temp = TaskHoldTemp::where('user_id', $user_id)->update(['type' => $c_type]);

                // make an comment
                Comment::create([
                    'activity_id' => $last_ongoing->id,
                    'user_id' => $user_id,
                    'comment' => $comment_title,
                    'comment_type' => $c_type,
                    'hasOngoingTask' => 0,
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['data' => $th], 400);
        }
    }

    public function restoreLastOngoingTask(Request $request)
    {
        $user_id = auth()->user()->id;
        $holdTasks = TaskHoldTemp::where('user_id', $user_id)->orderBy('created_at', 'DESC')->first();
        if (!isset($holdTasks)) {
            return response()->json(['message' => 'No hold job found!'], 400);
        }
        DB::beginTransaction();
        try {
            $holdTasks = Activity::where(['task_user_id' => $holdTasks->task_user_id, 'section_id' => 4])->orderBy('id', 'DESC')->first();
            $saved_activity = Activity::create([
                'task_user_id' => $holdTasks->task_user_id,
                'task_id' => $holdTasks->task_id,
                'user_id' => $user_id,
                'prev_section_id' => 4,
                'section_id' => 3,
                'qc_status' => $holdTasks->qc_status,
                'is_qc_task' => $holdTasks->is_qc_task,
            ]);

            TaskHoldTemp::where('user_id', $user_id)->delete();
            $last_comment = Comment::where(['user_id' => $user_id])->whereNotIn('comment_type', [1, 9])->orderBy('created_at', 'DESC')->first();
            if ($last_comment && $last_comment->isFinished == 0) {
                $last_comment->fresh()->update(['updated_at' => now(), 'isFinished' => 1]);
            }

            DB::commit();
            return response()->json(['data' => $holdTasks]);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['error' => $th]);
        }
    }

    public function activity_summary(Request $request)
    {
        $emp_user_id = $request->emp_user_id['id'];
        $emp_user = User::find($emp_user_id);
        $user_id = auth()->user()->id;
        $jobs = User::find($emp_user_id)->jobsActive(1)->orderBy('due_date', 'ASC')->get();
        $qc_jobs = User::find($emp_user_id)->jobsActive(2)->orderBy('due_date', 'ASC')->get();
        $assigned_jobs = [];
        $f_Activity = null;
        $current_date = date('Y-m-d');

        return DB::select("SELECT jobs.id, users.name, (CASE WHEN jobs.customer_name IS NULL THEN 'Quick job' ELSE jobs.customer_name END) AS customer_name, jobs.due_date, jobs.job_no, activities.created_at FROM activities
      INNER JOIN task_user ON activities.task_user_id = task_user.id
      INNER JOIN job_user ON task_user.job_user_id = job_user.id
      INNER JOIN jobs ON job_user.job_id = jobs.id
      INNER JOIN users ON activities.user_id = users.id
      WHERE activities.section_id = 3 AND users.id= " . $emp_user_id . " AND `activities`.`is_qc_task` <> 1
       AND DATE(activities.created_at) = DATE('" . $current_date . "')
      GROUP BY jobs.id ORDER BY activities.created_at DESC");
    }

    public function daily_tasks_summary(Request $request)
    {
        $job_user_id = $request->job_user_id;
        $a_jobs = DB::select("SELECT tasks.`task_no`, `task_user`.`due_date`, `task_user`.`plan_date`, `tasks`.`Time01`, `tasks`.`Time02`, `tasks`.`Time03`, activities.created_at, activities.`updated_at`
      FROM activities
            INNER JOIN task_user ON activities.task_user_id = task_user.id
            INNER JOIN tasks ON activities.`task_id` = tasks.id
            INNER JOIN job_user ON task_user.job_user_id = job_user.id
            INNER JOIN jobs ON job_user.job_id = jobs.id
            INNER JOIN users ON activities.user_id = users.id
            WHERE activities.section_id = 3 AND users.id= 2 AND `activities`.`is_qc_task` <> 1 AND jobs.id=6458
            AND DATE(activities.created_at) = DATE('2020-07-06')
            GROUP BY tasks.`task_no`
           ORDER BY activities.created_at DESC");

        return $a_jobs;

        foreach ($a_jobs as $key => $t_user) {
            if ($t_user->qc_id != 0) {
                $qc_user = User::find($t_user->qc_id);
            }
            $latest_ongoing = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id])->orderBy('id', 'DESC')->first();
            $finished_activity = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id, 'section_id' => 6])->orderBy('id', 'DESC')->first();
            $started_activity = Activity::select(['id', 'created_at', 'section_id', 'user_id', 'task_id', 'task_user_id'])->where(['task_user_id' => $t_user->id, 'section_id' => 3])->orderBy('created_at', 'ASC')->first();
            $all_ongoings = Activity::where(['task_user_id' => $t_user->id, 'section_id' => 3])->get();
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

            if ($latest_ongoing && $latest_ongoing->section_id == 3) {
                $sdate2 = Carbon::parse($latest_ongoing->created_at);
                $edate2 = Carbon::parse(now());
                $diff2 = $sdate2->diff($edate2);
                $months += $diff2->m;
                $days += $diff2->d;
                $hours += $diff2->h;
                $minutes += $diff2->i;
            }
            $task_status = 'Back Log';
            if ($latest_ongoing) {
                switch ($latest_ongoing->section_id) {
                    case 1:
                        $task_status = "Back Log";
                        break;
                    case 2:
                        $task_status = "Picked";
                        break;
                    case 3:
                        $task_status = "Ongoing";
                        break;
                    case 4:
                        $task_status = "Hold";
                        break;
                    case 5:
                        $task_status = "QC (" . explode(" ", $qc_user->name)[0] . ")";
                        break;
                    case 6:
                        $task_status = "Finished";
                        break;

                    default:
                        $task_status = "Back Log";
                        break;
                }
            }
            // $task_status = $t_user->status == 6 ? 'Finished' : 'Ongoing';
            $spent = $this->getSpentTime($months, $days, $hours, $minutes);
            $t_user->spent = $spent;
            // if ($days == 0 && $hours == 0 && $minutes == 0) {
            //   $task_status = "Not Started";
            // }
            $t_user->task_status = $task_status;
            $f_at = null;
            $s_at = null;
            if (isset($finished_activity)) {
                $f_at = $finished_activity->created_at;
            }
            if (isset($started_activity)) {
                $s_at = $started_activity->created_at;
            }
            $t_user->finished_at = $f_at;
            $t_user->started_at = $s_at;
        }
        // $sorted = collect($a_jobs);
        // $sorted->sortBy('id');
        return $a_jobs;
    }

    public function jobs_summary(Request $request)
    {
        $start = date_format(date_time_set(Carbon::parse(date("Y-m-d", strtotime($request->start))), 00, 00), 'Y-m-d H:i:s');
        $end = date_format(date_time_set(Carbon::parse(date("Y-m-d", strtotime($request->end))), 23, 59), 'Y-m-d H:i:s');

        $constraintJoin = "jobs.division_id";
        $constraint = "division_id";
        $division = $request->division;
        if ($request->division == -1) {
            $constraint = "";
            $division = "";
        }
        // $constraints = array_only($req, 'subject');
        // Received and Finished Job chart
        $receivedJobs = Job::all()
            ->where($constraint, $division)
            ->whereBetween('created_at', [$start, $end]);

        $finishedJobCount = 0;
        foreach ($receivedJobs as $job) {
            $jobs = DB::table('job_user')->where([
                ['job_id', '=', $job->id],
                ['jobtype_id', '=', 1],
            ])->whereBetween('updated_at', [$start, $end])->get();
            $finishedJobs = DB::table('job_user')->where([
                ['job_id', '=', $job->id],
                ['jobtype_id', '=', 1],
                ['status', '=', 6],
            ])->whereBetween('updated_at', [$start, $end])->get();
            if (count($jobs) != 0 && count($jobs) == count($finishedJobs)) {
                $finishedJobCount++;
            }
        }

        $respond = [
            "jobs" => [
                "labels" => [''],
                "datasets" => [
                    [
                        "label" => "Received Jobs",
                        "backgroundColor" => "#3d5afe",
                        "data" => [count($receivedJobs)],
                    ],
                    [
                        "label" => "Finished Jobs",
                        "backgroundColor" => "#00695c",
                        "data" => [$finishedJobCount],
                    ],
                ],
            ],
        ];
        // End Received and Finished Job chart

        //unfinished JObs Chart
        $taskedJobs = Task::select('tasks.id')
            ->join('jobs', 'tasks.job_id', '=', 'jobs.id')
            ->where($constraintJoin, $division)
            ->whereBetween('tasks.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')->get()->count();

        $assignedJobs = DB::table('job_user')
            ->join('jobs', 'job_user.job_id', '=', 'jobs.id')
            ->where($constraintJoin, $division)
            ->whereBetween('job_user.updated_at', [$start, $end])
            ->groupBy('job_user.job_id')->get()->count();

        $plannedJobs = JOB::SELECT('jobs.id')
            ->join('job_user', 'job_user.job_id', '=', 'jobs.id')
            ->join('task_user', 'task_user.job_user_id', '=', 'job_user.id')
            ->where($constraintJoin, $division)
            // ->where([
            //     ['jobs.division_id', '=', $request->division],
            // ])
            ->whereNotNull('task_user.plan_date')
            ->whereBetween('job_user.updated_at', [$start, $end])
            ->groupBy('job_user.job_id')->get()->count();

        $pickedJobs = JOB::SELECT('jobs.id')
            ->join('job_user', 'job_user.job_id', '=', 'jobs.id')
            ->join('task_user', 'task_user.job_user_id', '=', 'job_user.id')
            ->join('activities', 'task_user.id', '=', 'activities.task_user_id')
            ->where($constraintJoin, $division)
            // ->where([
            //     ['jobs.division_id', '=', $request->division],
            // ])
            ->whereBetween('job_user.updated_at', [$start, $end])
            ->groupBy('job_user.job_id')->get()->count();

        $respond = array_merge($respond, [
            "unfinishedJobs" => [
                "tasked" => $taskedJobs,
                "notTasked" => count($receivedJobs) - $taskedJobs,
                "assigned" => $assignedJobs,
                "notAssigned" => count($receivedJobs) - $assignedJobs,
                "notPlanned" => count($receivedJobs) - $plannedJobs,
                "planned" => $plannedJobs,
                "picked" => $pickedJobs,
                "notPicked" => count($receivedJobs) - $pickedJobs,
            ],
        ]);

        //Not Exceeded;
        // $planCount = 0; $estimateCount = 0; $dueDateCount = 0;

        $dueCount = Job::where([
            ['due_date', '>', date('y/m/d')],
            [$constraintJoin, '=', $division],
        ])->get()->count();
        $estimateCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.end_date')
            ->where([
                ['task_user.end_date', '>', date('y/m/d')],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.end_date', 'DESC')
            ->get()->count();

        $planCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.plan_date')
            ->where([
                ['task_user.plan_date', '>', date('y/m/d')],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.plan_date', 'DESC')
            ->get()->count();

        $notExceeded = [
            'plan' => $planCount,
            'estimate' => $estimateCount,
            'due' => $dueCount,
        ];

        $dueCount = Job::where('due_date', '<', date('y/m/d'))->get()->count();
        $planCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.plan_date')
            ->where([
                ['task_user.plan_date', '<', date('y/m/d')],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.plan_date', 'DESC')
            ->get()->count();

        $estimateCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.end_date')
            ->where([
                ['task_user.end_date', '<', date('y/m/d')],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.end_date', 'DESC')
            ->get()->count();

        $exceeded = [
            'plan' => $planCount,
            'estimate' => $estimateCount,
            'due' => $dueCount,
        ];

        $dueCount = Job::where([
            ['due_date', '<', date('Y/m/d', strtotime("-1 days"))],
            ['due_date', '>', date('Y/m/d', strtotime("-2 days"))],
            [$constraintJoin, '=', $division],
        ])->get()->count();

        $planCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.plan_date')
            ->where([
                ['task_user.end_date', '<', date('Y/m/d', strtotime("-1 days"))],
                ['task_user.end_date', '>', date('Y/m/d', strtotime("-2 days"))],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.plan_date', 'DESC')
            ->get()->count();

        $estimateCount = Task::select('tasks.job_id')
            ->join('task_user', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'jobs.id', '=', 'tasks.job_id')
            ->whereNotNull('task_user.end_date')
            ->where([
                ['task_user.end_date', '<', date('Y/m/d', strtotime("-1 days"))],
                ['task_user.end_date', '>', date('Y/m/d', strtotime("-2 days"))],
                [$constraintJoin, '=', $division],
            ])
            ->whereBetween('task_user.updated_at', [$start, $end])
            ->groupBy('tasks.job_id')
            ->orderBy('task_user.end_date', 'DESC')
            ->get()->count();

        $close = [
            'plan' => $planCount,
            'estimate' => $estimateCount,
            'due' => $dueCount,
        ];

        $respond = array_merge($respond, [
            'notExceeded' => $notExceeded,
            'exceeded' => $exceeded,
            'close' => $close,
        ]);

        //unfinished unfinished JObs Chart
        return $respond;
    }
}
