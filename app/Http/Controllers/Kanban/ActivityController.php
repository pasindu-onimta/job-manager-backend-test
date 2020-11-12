<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Activity;
use App\Task;
use App\Comment;
use App\User;
use App\Job;
use App\Notification;
use App\Employee;
use App\Feature;
use App\TaskAuthorization;
use App\TaskHoldTemp;
use Exception;

class ActivityController extends Controller
{
    public function store(Request $request)
    {
        $previousSection = (int) $request->previousSection;
        $newSection = (int) $request->newSection;
        $task_id = $request->task;
        $taskUserId = $request->taskUserId;
        $user_id = auth()->user()->id;
        $last_section_ids = [];
        $last_ongoing_job_id = '';
        $qc_task_assigned = false;
        $user_id_for_notifications = '';
        $job_id = Task::find($task_id)->job->id;
        DB::beginTransaction();

        $qc_enable = User::find($user_id)->jobsUser($job_id, $user_id)->orderBy('id', 'DESC')->first()->pivot->qc_enable;

        try {

            $allocated_jobs = User::find($user_id)->jobs;
            $data = [];

            // check section validation
            $backlogAllowedSections = [2];
            $pickedAllowedSections = [3];
            if ($qc_enable == 0) {
                $onGoingAllowedSections = [4, 5, 6];
            } else {
                $onGoingAllowedSections = [4, 5];
            }

            $holdAllowedSections = [3];
            $qcAllowedSections = [4, 3];
            $finishedAllowedSections = [];
            $bugsAllowedSections = [3];
            $passedAllowedSections = [3];

            $checkingSection = '';

            switch ($previousSection) {
                case 1:
                    $checkingSection = $backlogAllowedSections;
                    break;
                case 2:
                    $checkingSection = $pickedAllowedSections;
                    break;
                case 3:
                    $checkingSection = $onGoingAllowedSections;
                    break;
                case 4:
                    $checkingSection = $holdAllowedSections;
                    break;
                case 5:
                    $checkingSection = $qcAllowedSections;
                    break;
                case 6:
                    $checkingSection = $finishedAllowedSections;
                    break;
                case 7:
                    $checkingSection = $bugsAllowedSections;
                    break;
                case 8:
                    $checkingSection = $passedAllowedSections;
                    break;

                default:
                    // custom section validation
                    break;
            }
            if (in_array($newSection, $checkingSection)) {
                if ($previousSection == 5) {
                    $main_task = DB::table('task_user')->where(['id' => $taskUserId, 'status' => 1])->orderBy('created_at', 'DESC')->first();
                    if (isset($main_task)) {
                        $qc_id = $main_task->qc_id;
                        $task_qc = DB::table('task_user')->where(['user_id' => $qc_id, 'task_id' => $task_id, 'assign_by' => $user_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();
                        if (isset($task_qc)) {
                            $task_qc_user_id = $task_qc->id;
                            $task_qc_activity = Activity::where(['task_user_id' => $task_qc_user_id])->orderBy('created_at', 'DESC')->first();
                            if (isset($task_qc_activity)) {
                                // if (1 < $task_qc_activity->section_id) {
                                if ($task_qc_activity->section_id == 2 || $task_qc_activity->section_id == 3 || $task_qc_activity->section_id == 4) {
                                    return response()->json(['status' => 5, 'message' => 'This task is under qc mode'], 400);
                                }
                            } else {
                                DB::table('task_user')->where(['id' => $task_qc->id])->orderBy('created_at', 'DESC')->update(['status' => 0]);
                                $all_qc_tasks = DB::table('task_user')->where(['job_user_id' => $task_qc->job_user_id, 'status' => 1])->get();
                                if ($all_qc_tasks->isEmpty()) {
                                    DB::table('job_user')->where(['id' => $task_qc->job_user_id])->orderBy('created_at', 'DESC')->update(['status' => 0]);
                                }
                            }
                        }
                    }
                }

                if ($newSection == 3) {


                    $data = [];
                    $moved_task = Activity::where(['task_user_id' => $taskUserId])->orderBy('created_at', 'DESC')->first();

                    $tt = DB::table('task_user')->where(['id' => $taskUserId])->latest()->first();
                    if ($tt && $tt->qc_id != 0) {
                        // check and apply features
                        if (Feature::find(1)->status == 1) {

                            $hasAuthorizedMain = TaskAuthorization::where('job_user_id', $tt->job_user_id)
                                ->whereDate('created_at', now())
                                ->orderBy('created_at', 'DESC')
                                ->first();

                            if (!$hasAuthorizedMain) {
                                DB::rollback();
                                return response()->json(['status' => 8, 'message' => 'You need to approve this task befor begin'], 400);
                            } else {
                                if ($hasAuthorizedMain->entire_job != 1) {
                                    $hasAuthorized = TaskAuthorization::where('task_user_id', $moved_task->task_user_id)->whereDate('created_at', now())
                                        ->orderBy('created_at', 'DESC')
                                        ->first();
                                    if (!$hasAuthorized) {
                                        DB::rollback();
                                        return response()->json(['status' => 8, 'message' => 'You need to approve this task befor begin'], 400);
                                    }
                                }
                            }
                        }
                    }

                    $all_assign_tasks = DB::table('task_user')->where(['user_id' => $user_id, 'status' => 1])->get();
                    if (!$all_assign_tasks->isEmpty()) {
                        foreach ($all_assign_tasks as $key => $aTasks) {
                            $result = Activity::where(['task_user_id' => $aTasks->id])->orderBy('created_at', 'DESC')->first();
                            if (isset($result)) {
                                if ($result->section_id == 3 && $result->task->job_id == $moved_task->task->job_id) {
                                    if ($result->is_qc_task == 1) {
                                        DB::rollback();
                                        return response()->json(['status' => 2, 'message' => 'This task is already in qc ongoing section'], 400);
                                    }
                                    DB::rollback();
                                    return response()->json(['status' => 2, 'message' => 'There is a task already in ongoing section'], 400);
                                } else if ($result->section_id == 3 && $result->task->job_id != $moved_task->task->job_id) {
                                    DB::rollback();
                                    return response()->json(['status' => 1, 'message' => 'There is a task already running on another job'], 400);
                                }
                            }
                        }
                    }

                    $moved_task_details = DB::table('task_user')->where(['id' => $taskUserId, 'status' => 1])->orderBy('created_at', 'DESC')->first();
                    if (isset($moved_task_details)) {
                        if ($moved_task_details->qc_id == 0) {
                            $all_assign_tasks_users = DB::table('task_user')->where(['task_id' => $task_id, ['qc_id', '!=', 0], ['user_id', '!=', $user_id], 'status' => 1])->get();
                            if (!$all_assign_tasks_users->isEmpty()) {
                                foreach ($all_assign_tasks_users as $key => $aTasks) {
                                    $result = Activity::where(['task_user_id' => $aTasks->id, 'is_qc_task' => 1])->orderBy('created_at', 'DESC')->first();
                                    if (isset($result)) {
                                        if (($result->section_id == 3 || $result->section_id == 5) && $result->user_id != $user_id) {
                                            DB::rollback();
                                            return response()->json(['status' => 2, 'message' => 'This task is currently doing by someone else'], 400);
                                        }
                                    }
                                }
                            }
                        } else if ($moved_task_details->qc_id != 0) {
                            $all_assign_tasks_users = DB::table('task_user')->where(['task_id' => $task_id, 'status' => 1])->get();
                            if (!$all_assign_tasks_users->isEmpty()) {
                                foreach ($all_assign_tasks_users as $key => $aTasks) {
                                    $result = Activity::where(['task_user_id' => $aTasks->id])->orderBy('created_at', 'DESC')->first();
                                    if (isset($result)) {
                                        if (($result->section_id == 3 || $result->section_id == 5) && $result->user_id != $user_id) {
                                            DB::rollback();
                                            return response()->json(['status' => 2, 'message' => 'This task is currently doing by someone else'], 400);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    TaskHoldTemp::where('user_id', $user_id)->delete();
                    $last_comment = Comment::where(['user_id' => $user_id, ['comment_type', '!=', 9]])->orderBy('created_at', 'DESC')->first();
                    if ($last_comment && $last_comment->isFinished == 0) {
                        $last_comment->fresh()->update(['updated_at' => now(), 'isFinished' => 1]);
                    }
                } else if ($newSection == 5) {
                    $qc_task_assigned = true;
                    $a_task = DB::table('task_user')->where(['user_id' => $user_id, 'task_id' => $task_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();
                    $qc_id = $a_task->qc_id;
                    $user_id_for_notifications = $qc_id;
                    $assigend_by = $a_task->user_id;
                    $due_date = $a_task->due_date;
                    $notification_type = "New job (QC)";
                    $job = Job::find($job_id);

                    $check_job_user = DB::table('job_user')->where(['user_id' => $qc_id, 'job_id' => $job_id, 'jobtype_id' => 2, 'status' => 1])->orderBy('created_at', 'DESC')->first();

                    if (!isset($check_job_user)) {
                        $job_user = DB::table('job_user')->insertGetId(['user_id' => $qc_id, 'job_id' => $job_id, 'created_at' => now(), 'updated_at' => now(), 'jobtype_id' => 2]);


                        $saved_task_user = DB::table('task_user')->insertGetId([
                            'user_id' => $qc_id,
                            'task_id' => $task_id,
                            'job_user_id' => $job_user,
                            'qc_id' => 0,
                            'priority' => 1,
                            'assign_date' => now(),
                            'assign_by' => $assigend_by,
                            'due_date' => $due_date,
                            'plan_date' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $notification = Notification::create([
                            'user_id' => $qc_id,
                            'job_user_id' => $job_user,
                            'task_user_id' => $saved_task_user,
                            'job_id' => $job_id,
                            'assigend_by' => $assigend_by,
                            'notification_type' => 2,
                            'title' => $notification_type,
                            'count' => 10,
                            'description' => $job->job,
                            'status' => 1,
                        ]);
                    } else {
                        $saved_task_user = DB::table('task_user')->insertGetId([
                            'user_id' => $qc_id,
                            'task_id' => $task_id,
                            'job_user_id' => $check_job_user->id,
                            'qc_id' => 0,
                            'priority' => 1,
                            'assign_date' => now(),
                            'assign_by' => $assigend_by,
                            'due_date' => $due_date,
                            'plan_date' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $notification = Notification::create([
                            'user_id' => $qc_id,
                            'job_user_id' => $check_job_user->id,
                            'task_user_id' => $saved_task_user,
                            'job_id' => $job_id,
                            'assigend_by' => $assigend_by,
                            'notification_type' => 2,
                            'title' => $notification_type,
                            'count' => 10,
                            'description' => $job->job,
                            'status' => 1,
                        ]);
                    }
                } else if ($newSection == 6) {
                    $main_task = DB::table('task_user')->where(['id' => $taskUserId, 'status' => 1])->orderBy('id', 'DESC')->first();
                    DB::table('task_user')->where(['id' => $taskUserId, 'status' => 1])->orderBy('id', 'DESC')->update(['status' => 6, 'updated_at' => now()]);

                    $task_user_all = DB::table('task_user')->where(['user_id' => $user_id, 'job_user_id' => $main_task->job_user_id, 'status' => 1])->get();

                    if ($task_user_all->isEmpty()) {
                        DB::table('job_user')->where(['id' => $main_task->job_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->update(['status' => 6, 'updated_at' => now()]);
                    }
                }


                if ($previousSection != 1) {
                    $last_activity = Activity::where(['task_id' => $task_id, 'section_id' => $previousSection])->orderBy('created_at', 'DESC')->first()->update(['updated_at' => now()]);
                }

                Task::where('id', $task_id)->update(['current_status' => $newSection]);
                $task_users = DB::table('task_user')->where(['id' => $taskUserId, 'status' => 1])->orderBy('created_at', 'DESC')->first();
                $is_qc_task = 0;
                if (isset($task_users)) {
                    if ($task_users->qc_id == 0) {
                        $is_qc_task = 1;
                    }
                }

                $abc = Activity::create([
                    'task_user_id' => $taskUserId,
                    'task_id' => $task_id,
                    'user_id' => $user_id,
                    'prev_section_id' => $previousSection,
                    'section_id' => $newSection,
                    'qc_status' => 0,
                    'is_qc_task' => $is_qc_task,
                ]);

                // update last comment
                TaskHoldTemp::where('user_id', $user_id)->delete();
                $last_comment = Comment::where(['user_id' => $user_id])->whereNotIn('comment_type', [1, 9])->orderBy('created_at', 'DESC')->first();
                if ($last_comment && $last_comment->isFinished == 0) {
                    $last_comment->fresh()->update(['updated_at' => now(), 'isFinished' => 1]);
                }

                DB::commit();
                return response()->json(['message' => 'Success', 'isQcTaskAssignd' => $qc_task_assigned, 'user_id' => $user_id_for_notifications], 201);
            } else {
                DB::rollback();
                return response()->json(['status' => 4, 'message' => 'Invalid Section'], 400);
            }
        } catch (\Exception  $th) {
            return response()->json(['data' => $th]);
            DB::rollback();
        }
    }

    public function checkSectionValidation()
    {
        # code...
    }

    public function storeComments(Request $request)
    {
        $user_id = auth()->user()->id;
        $comment = $request->comment;
        $task_id = $request->task_id;
        $image = Employee::where('id', auth()->user()->employee_id)->get()->first()->image;

        // get last activity id of selected task

        $result = DB::select("SELECT activities.id FROM activities
        INNER JOIN tasks ON (activities.task_id = tasks.id) 
        WHERE tasks.id = " . $task_id . " AND activities.qc_status != 1
        ORDER BY activities.id DESC LIMIT 0, 1");

        if (isset($result[0])) {
            $last_activity_id = $result[0]->id;

            // save data to comments table

            $storedComment = Comment::create([
                'activity_id' => $last_activity_id,
                'user_id' => $user_id,
                'comment' => $comment,
                'comment_type' => 1,
            ]);

            // logged user name
            $logged_user_name = auth()->user()->name;
            $storedComment->name = $logged_user_name;
            $storedComment->image = $image;
            return $storedComment;
        } else {
            return 'naaa';
        }
    }
    public function change(Request $request)
    {
        $user_id = auth()->user()->id;
        $previousSection = (int) $request->previousSection;
        $newSection = (int) $request->newSection;
        $task_id = $request->task;
        $allocated_tasks = User::find($user_id)->tasksActiveAll;


        // $last_activity = Activity::where(['task_id'=> $task_id, 'section_id' => $previousSection])->orderBy('created_at', 'DESC')->first();
        $hold_task_activity_id = '';

        DB::beginTransaction();

        try {
            foreach ($allocated_tasks as $key => $task) {
                $result = Activity::where(['user_id' => $user_id, 'task_id' => $task->id])->get()->last();
                if ($result && $result->section_id == 3) {
                    Task::where('id', $task->id)->update(['current_status' => 4]);
                    $result->update(['updated_at' => now()]);
                    $last_activity_task_user = DB::table('task_user')->where(['id' => $result->task_user_id])->first();
                    // update old activity's section as a new record
                    $saved_activity = Activity::create([
                        'task_user_id' => $last_activity_task_user->id,
                        'task_id' => $last_activity_task_user->task_id,
                        'user_id' => $last_activity_task_user->user_id,
                        'prev_section_id' => 3,
                        'section_id' => 4,
                    ]);
                    $hold_task_activity_id = $saved_activity->id;
                }
            }


            if ($previousSection != 1) {
                $last_activity = Activity::where(['task_id' => $task_id, 'section_id' => $previousSection])->orderBy('created_at', 'DESC')->first();
                $last_activity->update(['updated_at' => now()]);
            }

            Task::where('id', $task_id)->update(['current_status' => $newSection]);
            $task_user = DB::table('task_user')->where(['user_id' => $user_id, 'task_id' => $task_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            $saved_activity = Activity::create([
                'task_user_id' => $task_user->id,
                'task_id' => $task_id,
                'user_id' => $user_id,
                'prev_section_id' => $previousSection,
                'section_id' => $newSection,
            ]);


            // make an comment
            Comment::create([
                'activity_id' => $hold_task_activity_id,
                'user_id' => $user_id,
                'comment' => 'Switch to anothor job (' . Task::find($task_id)->job->job_no . ')',
                'comment_type' => 1
            ]);

            DB::commit();
            return response()->json(['message' => 'Success'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => 'error'], 400);
        }
    }

    public function changeQuickJobsToOngoing(Request $request)
    {
        $user_id = auth()->user()->id;
        $task_user_id = $request->task_user_id;
        $job_user_id = $request->job_user_id;
        $allocated_tasks = User::find($user_id)->tasksActiveAll;
        $task_user = DB::table('task_user')->where(['id' => $task_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();
        $custom_date = Carbon::now()->addSecond();
        $hold_task_activity_id = '';

        DB::beginTransaction();

        try {
            $saved_activity_1 = Activity::create([
                'task_user_id' => $task_user_id,
                'task_id' => $task_user->task_id,
                'user_id' => $user_id,
                'prev_section_id' => 1,
                'section_id' => 2,
                'updated_at' => $custom_date
            ]);

            foreach ($allocated_tasks as $key => $task) {
                $result = Activity::where(['user_id' => $user_id, 'task_id' => $task->id])->get()->last();
                if ($result && $result->section_id == 3) {
                    Task::where('id', $task->id)->update(['current_status' => 4]);
                    $result->update(['updated_at' => $custom_date]);
                    $last_activity_task_user = DB::table('task_user')->where(['id' => $result->task_user_id])->first();

                    // return ['data' => $last_activity_task_user];
                    // update old activity's section as a new record
                    $saved_activity = Activity::create([
                        'task_user_id' => $last_activity_task_user->id,
                        'task_id' => $last_activity_task_user->task_id,
                        'user_id' => $last_activity_task_user->user_id,
                        'prev_section_id' => 3,
                        'section_id' => 4,
                        'created_at' => $custom_date,
                        'updated_at' => $custom_date,
                    ]);
                    $hold_task_activity_id = $saved_activity->id;

                    // make an comment
                    Comment::create([
                        'activity_id' => $hold_task_activity_id,
                        'user_id' => $user_id,
                        'comment' => 'Switch to anothor job (' . Task::find($task_user->task_id)->job->job_no . ')',
                        'comment_type' => 1
                    ]);
                }
            }

            // if ($previousSection != 1) {
            //     $last_activity = Activity::where(['task_id'=> $task_id, 'section_id' => $previousSection])->orderBy('created_at', 'DESC')->first();
            //     $last_activity->update(['updated_at' => now()]);
            // }

            // Task::where('id', $task_id)->update(['current_status' => $newSection]);






            $saved_activity_2 = Activity::create([
                'task_user_id' => $task_user_id,
                'task_id' => $task_user->task_id,
                'user_id' => $user_id,
                'prev_section_id' => 2,
                'section_id' => 3,
                'created_at' => $custom_date,
                'updated_at' => $custom_date,
            ]);

            // update last comment
            TaskHoldTemp::where('user_id', $user_id)->delete();
            $last_comment = Comment::where(['user_id' => $user_id])->whereNotIn('comment_type', [1, 9])->orderBy('created_at', 'DESC')->first();
            if ($last_comment && $last_comment->isFinished == 0) {
                $last_comment->fresh()->update(['updated_at' => now(), 'isFinished' => 1]);
            }




            DB::commit();
            return response()->json(['message' => 'Success'], 201);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
    }
    public function changeQcTask(Request $request)
    {
        $user_id = auth()->user()->id;
        $previousSection = (int) $request->previousSection;
        $newSection = (int) $request->newSection;
        $qc_message = $request->qc_message;
        $task_id = $request->task;
        $allocated_jobs = User::find($user_id)->jobs;
        $data = [];
        DB::beginTransaction();


        try {
            $last_activity = Activity::where(['task_id' => $task_id, 'section_id' => $previousSection])->orderBy('created_at', 'DESC')->first();

            $last_activity->update(['updated_at' => now()]);

            Task::where('id', $task_id)->update(['current_status' => $newSection]);

            // add qc activity
            $saved_activity = Activity::create([
                'task_user_id' => $last_activity->task_user_id,
                'task_id' => $task_id,
                'user_id' => $user_id,
                'prev_section_id' => $previousSection,
                'section_id' => $newSection,
                'qc_status' => 0,
                'is_qc_task' => 1,
            ]);

            $task_user_id = $last_activity->task_user_id;
            $task_qc_user = DB::table('task_user')->where(['id' => $task_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            $task_user = DB::table('task_user')->where(['user_id' => $task_qc_user->assign_by, 'qc_id' => $user_id, 'task_id' => $task_id, 'status' => 1])->orderBy('created_at', 'DESC')->first();

            DB::table('task_user')->where(['id' => $task_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->update(['status' => 0, 'updated_at' => now()]);
            $task_users_available = DB::table('task_user')->where(['job_user_id' => $task_qc_user->job_user_id, 'status' => 1])->get();
            if ($task_users_available->isEmpty()) {
                DB::table('job_user')->where(['id' => $task_qc_user->job_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->update(['status' => 0, 'updated_at' => now()]);
            }

            // add developer activity
            if ($newSection == 7) {

                $comment_type = 3;
                Activity::create([
                    'task_user_id' => $task_user->id,
                    'task_id' => $task_id,
                    'user_id' => $task_user->user_id,
                    'prev_section_id' => $newSection,
                    'section_id' => 5,
                    'qc_status' => 1,
                    'is_qc_task' => 0,
                ]);
            } else if ($newSection == 8) {
                DB::table('task_user')->where(['id' => $task_user->id])->update(['status' => 6, 'updated_at' => now()]);

                $task_user_all = DB::table('task_user')->where(['user_id' => $task_qc_user->assign_by, 'job_user_id' => $task_user->job_user_id, 'status' => 1])->get();

                if ($task_user_all->isEmpty()) {
                    DB::table('job_user')->where(['id' => $task_user->job_user_id, 'status' => 1])->orderBy('created_at', 'DESC')->update(['status' => 6, 'updated_at' => now()]);
                }

                $comment_type = 4;
                Activity::create([
                    'task_user_id' => $task_user->id,
                    'task_id' => $task_id,
                    'user_id' => $task_user->user_id,
                    'prev_section_id' => $newSection,
                    'section_id' => 6,
                    'qc_status' => 1,
                    'is_qc_task' => 0,
                ]);
            }

            Comment::create([
                'activity_id' => $saved_activity->id,
                'user_id' => $user_id,
                'comment' => $qc_message,
                'comment_type' => $comment_type,
            ]);

            DB::commit();
            return response()->json(['message' => 'Success'], 201);
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json(['message' => 'error']);
        }
    }
}
