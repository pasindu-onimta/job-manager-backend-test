<?php

namespace App\Http\Controllers\Kanban;

use App\Activity;
use App\AuthorizationRequest;
use App\Customer;
use App\Division;
use App\Http\Controllers\Controller;
use App\Job;
use App\JobDueDateChange;
use App\Setting;
use App\Task;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class JobsController extends Controller
{
    public function loadAllJobs()
    {
        // $jobs = Job::where('status', 4)->orderBy('id', 'DESC')->get();
        // foreach ($jobs as $key => $job) {
        //     $job_due_date = Carbon::parse($job->due_date);
        //     $current_date = Carbon::now()->subDay();
        //     if ($current_date->gt($job_due_date)) {
        //         $job->isJobExpired = true;
        //     } else {
        //         $job->isJobExpired = false;
        //     }
        // }

        // return $jobs;
        $jobs = [];
        $unassignedTasks = Task::where(['isAssigned' => 0, ['Time02', '!=', 0]])->orderBy('id', 'DESC')->groupBy('job_id')->get();
        foreach ($unassignedTasks as $key => $task) {
            $job = Job::find($task->job_id);
            $job_due_date = Carbon::parse($job->due_date);
            $current_date = Carbon::now()->subDay();
            if ($current_date->gt($job_due_date)) {
                $job->isJobExpired = true;
            } else {
                $job->isJobExpired = false;
            }
            array_push($jobs, $job);
        }

        return $jobs;
    }

    public function loadAllJobsForAdmin()
    {
        $jobs = Job::where('status', '!=', 6)->orderBy('created_at', 'DESC')->get();
        return $jobs;
    }

    public function loadJobDetails($id)
    {
        $job_id = $id;
        $job = Job::find($job_id);
        $data = [];
        $tasks = Task::with('users')->select('id', 'task_no', 'task_description', 'Time02', 'isAssigned')->where(['job_id' => $job_id, ['Time02', '!=', 0]])
            ->orderBy('created_at', 'DESC')->get();

        $global_ongoing_months = 0;
        $global_ongoing_days = 0;
        $global_ongoing_hours = 0;
        $global_ongoing_minutes = 0;

        $global_allocated_time = 0;
        foreach ($tasks as $key => $task) {
            $task_status = 'Back Log';
            $task->total_duration = null;
            $ts = Activity::select('section_id')->where(['task_id' => $task->id])
                ->orderBy('id', 'DESC')->first();
            $ts = DB::select("SELECT activities.section_id FROM `activities`
                INNER JOIN task_user ON `activities`.`task_user_id` = `task_user`.id
                WHERE activities.task_id='$task->id' AND task_user.`qc_id` <> 0
                ORDER BY activities.id DESC LIMIT 1");

            $global_allocated_time += $task->Time02;

            if (!empty($ts)) {
                switch ($ts[0]->section_id) {
                    case '2':
                        $task_status = 'Picked';
                        break;
                    case '3':
                        $task_status = 'Ongoing';
                        break;
                    case '4':
                        $task_status = 'Hold';
                        break;
                    case '5':
                        $task_status = 'QC';
                        break;
                    case '6':
                        $task_status = 'Finished';
                        break;

                    default:
                        $task_status = 'Back Log';
                        break;
                }
            } else {
                $task_status = 'Back Log';
            }

            $task->current_status = $task_status;

            if ($task->isAssigned == '1') {
                $ongoing_months = 0;
                $ongoing_days = 0;
                $ongoing_hours = 0;
                $ongoing_minutes = 0;
                foreach ($task->users as $key => $t_user) {
                    $activities = Activity::where(['task_user_id' => $t_user->pivot->id, 'section_id' => 3])->get();
                    foreach ($activities as $key => $ongoing) {
                        $sdate = Carbon::parse($ongoing->created_at);
                        $edate = Carbon::parse($ongoing->updated_at);
                        $diff = $sdate->diff($edate);
                        $ongoing_months += $diff->m;
                        $ongoing_days += $diff->d;
                        $ongoing_hours += $diff->h;
                        $ongoing_minutes += $diff->i;
                    }
                }
                $global_ongoing_months += $ongoing_months;
                $global_ongoing_days += $ongoing_days;
                $global_ongoing_hours += $ongoing_hours;
                $global_ongoing_minutes += $ongoing_minutes;
                $ongoing_spent = $this->getSpentTime($ongoing_months, $ongoing_days, $ongoing_hours, $ongoing_minutes);
                $task->total_duration = $ongoing_spent;
            } else {
                $task_status = 'Not Assigned';
            }
            $task->current_status = $task_status;
            array_push($data, $task);
        }
        $total_spent_time = $this->getSpentTime($global_ongoing_months, $global_ongoing_days, $global_ongoing_hours, $global_ongoing_minutes);
        return ['tasks' => $tasks, 'total_spent_time' => $total_spent_time, 'global_allocated_time' => $global_allocated_time];
    }

    public function getSpentTime($months, $days, $hours, $minutes)
    {
        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return ['months' => $months, 'days' => sprintf("%02d", $days), 'hours' => sprintf("%02d", $hours), 'minutes' => sprintf("%02d", $minutes)];
    }

    public function loadAllJobsForUser($id)
    {
        $jobs = User::find($id)->jobsActive(1)->orderBy('id', 'DESC')->get();
        return $jobs;
    }

    public function index()
    {
        $user_id = auth()->user()->id;
        $jobs = User::find($user_id)->jobsActive(1)->orderBy('due_date', 'ASC')->get();
        $assigned_jobs = [];

        foreach ($jobs as $key => $job) {
            $job->isActive = false;
            $a_jobs = DB::table('job_user')->where(['user_id' => $user_id, 'job_id' => $job->id, 'jobtype_id' => 1, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            if (isset($a_jobs)) {
                $task_user_data = auth()->user()->tasksJobActiveAll($a_jobs->id)->get();
                foreach ($task_user_data as $key => $tud) {
                    $last_activity = Activity::where('task_user_id', $tud->pivot->id)->orderBy('created_at', 'DESC')->first();
                    if (isset($last_activity) && $last_activity->section_id == 3) {
                        $job->isActive = true;
                    }
                }
                $job->job_user = $a_jobs;
                array_push($assigned_jobs, $job);
            }
        }
        return $assigned_jobs;
    }

    public function indexQC()
    {
        $user_id = auth()->user()->id;
        $jobs = User::find($user_id)->jobsActive(2)->orderBy('due_date', 'ASC')->get();
        $assigned_jobs = [];
        foreach ($jobs as $key => $job) {
            $a_jobs = DB::table('job_user')->where(['user_id' => $user_id, 'job_id' => $job->id, 'jobtype_id' => 2, 'status' => 1])->orderBy('created_at', 'DESC')->first();
            if (isset($a_jobs)) {
                $job->job_user = $a_jobs;
                array_push($assigned_jobs, $job);
            }
        }
        return $assigned_jobs;
    }

    public function jobs($id)
    {
        // $user = auth()->user();
        // return $user->job$jobs;
    }

    public function syncJobs()
    {

        $max_job_id = Job::max('last_id');
        $max_task_id = Task::max('last_id');
        if (!$max_job_id) {
            $max_job_id = 0;
        }
        if (!$max_task_id) {
            $max_task_id = 0;
        }

        $jobs = Http::post('http://onimtait.dyndns.info:9000/api/AndroidApi/CommonExecute', [
            // $jobs = Http::post('http://192.168.1.60:9000/api/AndroidApi/CommonExecute', [
            'SpName' => 'API_sp_CommonExecute',
            'HasReturnData' => 'T',
            'Parameters' => [
                [
                    'Para_Name' => '@Iid',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => '3',
                ], [
                    'Para_Name' => '@MaxId',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => $max_job_id, //$max_job_id
                ],
            ],
        ])->json()['CommonResult']['Table'];

        // return $jobs;

        $tasks = Http::post('http://onimtait.dyndns.info:9000/api/AndroidApi/CommonExecute', [
            // $tasks = Http::post('http://192.168.1.60:9000/api/AndroidApi/CommonExecute', [
            'SpName' => 'API_sp_CommonExecute',
            'HasReturnData' => 'T',
            'Parameters' => [
                [
                    'Para_Name' => '@Iid',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => '4',
                ], [
                    'Para_Name' => '@MaxId',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => $max_task_id, //$max_task_id
                ],
            ],
        ])->json()['CommonResult']['Table'];

        // return $tasks;

        $col = collect($jobs);
        $col2 = collect($tasks);

        if ($col->isEmpty() && $col2->isEmpty()) {
            return response()->json(['message' => 'System is up to date', 'status' => 1], 200);
        }

        DB::beginTransaction();
        try {
            foreach ($jobs as $key => $job) {
                $priority = 0;
                switch (trim($job['priority'])) {
                    case 'Urgent':
                        $priority = 1;
                        break;
                    case 'Within a week':
                        $priority = 2;
                        break;
                    default:
                        $priority = 3;
                        break;
                }

                Job::updateOrCreate([
                    'job_no' => $job['job_id'],
                ], [
                    'job_no' => str_replace(' ', '', $job['job_id']),
                    'job_description' => $job['job_descri'],
                    'customer_name' => $job['cus_name'],
                    'customer_id' => str_replace(' ', '', $job['cus_id']),
                    'system' => $job['system'],
                    'contact_person' => $job['con_person'],
                    'contact_number' => $job['con_nu'],
                    'priority' => $priority,
                    'due_date' => $job['due_date'],
                    'status' => 4,
                    'last_id' => $job['id_no'],
                    'created_at' => now(),
                    'updated_at' => $job['job_date'],
                ]);
            }

            foreach ($tasks as $key => $task) {
                $job = Job::where('job_no', str_replace(' ', '', $task['JobNu']))->get()->last();
                if (isset($job)) {
                    Task::updateOrCreate(
                        [
                            'job_id' => $job->id,
                            'task_no' => $task['TaskNu'],
                        ],
                        [
                            'job_id' => $job->id,
                            'task_no' => $task['TaskNu'],
                            'task_name' => $task['Task'],
                            'task_description' => $task['Task'],
                            'Time01' => $task['Time01'],
                            'Time02' => $task['Time02'],
                            'Time03' => $task['Time03'],
                            'TotTime01' => $task['TotTime01'],
                            'TotTime02' => $task['TotTime02'],
                            'TotTime03' => $task['TotTime03'],
                            'last_id' => $task['id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
            DB::commit();
            return response()->json(['message' => 'Success', 'status' => 2], 201);
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json(['message' => 'Sync Error', 'error' => $th], 500);
        }
    }

    public function storeQuickJob(Request $request)
    {
        $user_id = auth()->user()->id;
        $job_assign_by = $request->job_assign_by;
        $job_description = $request->job_description;
        $chargeable = $request->chargeable;
        $qc = $request->qc;
        $job_type = $request->job_type;
        $selected_customer = $request->selected_customer;
        $customer = null;

        if (isset($selected_customer)) {
            $customer = Customer::find($selected_customer);
        }
        $job_no = "";

        DB::beginTransaction();

        try {
            $settings = Setting::where('id_type', $job_type)->get()->first();
            $last_id = $settings->last_id;

            switch ($job_type) {
                case 1:
                    $job_no = "QJ-";
                    break;
                case 2:
                    $job_no = "TS-";
                    break;
                case 3:
                    $job_no = "OW-";
                    break;
            }
            $new_id = $last_id + 1;
            $generated_job_no = $job_no . sprintf("%04d", $new_id);
            $settings->update(['last_id' => $new_id]);

            $new_job = Job::create([
                'job_no' => $generated_job_no,
                'job_description' => $job_description,
                'customer_name' => $customer ? $customer->customer_name : null,
                'customer_id' => $customer ? $customer->customer_code : null,
                'chargeable' => $chargeable,
                'priority' => 5,
                'due_date' => date("Y-m-d"),
            ]);

            $new_task = Task::create([
                'job_id' => $new_job->id,
                'task_no' => 1,
                'task_name' => $job_description,
                'task_description' => $job_description,
                'Time01' => 1,
                'Time02' => 1,
                'Time03' => 1,
                'TotTime01' => 1,
                'TotTime02' => 1,
                'TotTime03' => 1,
                'isAssigned' => 1,
            ]);

            $new_job_user = DB::table('job_user')->insertGetId([
                'user_id' => $user_id,
                'job_id' => $new_job->id,
                'created_at' => now(),
                'updated_at' => now(),
                'jobtype_id' => 1,
                'qc_enable' => $qc ? 1 : 0,
            ]);

            $new_task_user = DB::table('task_user')->insertGetId([
                'job_user_id' => $new_job_user,
                'user_id' => $user_id,
                'task_id' => $new_task->id,
                'qc_id' => $user_id,
                'priority' => 1,
                'assign_date' => now(),
                'assign_by' => $user_id,
                'due_date' => date("Y-m-d"),
                'plan_date' => date("Y-m-d"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return ['job_no' => $generated_job_no, 'task_user_id' => $new_task_user, 'new_job_user' => $new_job_user];
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            //formatting the Date
            $job = $request->all();
            $job['due_date'] = date("Y-m-d", strtotime($request->due_date));

            // generating job_no
            $year = date("Y");
            $job_setting = Setting::find(4); //4 is for the job instance.
            $job['job_no'] = Division::find($job['division_id'])->job_code . substr(strval($year), 2) . "-" . sprintf("%04d", $job_setting->last_id);
            $job_setting->last_id++;
            $job_setting->save();

            // save the Job
            $request->replace($job);
            $newJob = Job::create($request->all());

            $response = Job::with('division', 'customer', 'employee', 'jobPriority')->find($newJob->id);
            DB::commit();
            return response()->json(['message' => "Job Created", 'job' => $response], 200);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e], 500);
        }
    }

    public function getAllJobs(Request $request)
    {
        switch ($request->sort['field']) {
            case 'customer.customer_name':
                $field = 'customers.customer_name';
                break;
            case "job_priority.name":
                $field = "jobs.priority";
                break;
            case "employee.name":
                $field = "users.name";
                break;
            case "":
                $field = "jobs.created_at";
                break;
            case "actions":
                $field = "jobs.created_at";
                break;
            default:
                $field = $request->sort['field'];
        }

        $count = Job::select('jobs.id')
            ->leftJoin('customers', 'jobs.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'jobs.employee_id', '=', 'users.id')
            ->Where('jobs.job_no', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_name', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_code', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_name', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_code', 'like', "%$request->keyword%")
            ->orWhere('jobs.due_date', 'like', "%$request->keyword%")
            ->orWhere('jobs.job_description', 'like', "%$request->keyword%")
            ->orWhere('users.name', 'like', "%$request->keyword%")->get()->count();

        $jobs = Job::with('division:id,name', 'customer:id,customer_name', 'employee:id,name', 'jobPriority', 'jobCoordinator:id,name', 'requestedEmployee:id,name')
            ->select('jobs.*')
            ->leftJoin('customers', 'jobs.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'jobs.employee_id', '=', 'users.id')
            ->Where('jobs.job_no', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_name', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_code', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_name', 'like', "%$request->keyword%")
            ->orWhere('customers.customer_code', 'like', "%$request->keyword%")
            ->orWhere('jobs.due_date', 'like', "%$request->keyword%")
            ->orWhere('jobs.job_description', 'like', "%$request->keyword%")
            ->orWhere('users.name', 'like', "%$request->keyword%")
            ->orderBy($field, $request->sort['type'])
            ->paginate($request->records);

        // $jobs = Job::with('division:id,name', 'customer:id,customer_name', 'employee:id,name', 'jobPriority:id,name')
        //     ->withCustomer($request->keyword)
        //     ->where('job_no', 'like', "%$request->keyword%")
        //     ->orderBy(($field == '') ? 'created_at' : $field, $request->sort['type'])
        //     ->paginate($request->records);

        // ->load('division:id,name', 'customer:id,customer_name', 'employee:id,name', 'jobPriority:id,name');
        return response()->json(["count" => $count, "jobs" => $jobs]);
    }

    public function updateJob(Request $request)
    {
        try {
            $job = Job::find($request->id);
            if ($job) {
                //formatting the Date
                $newJob = $request->all();
                $newJob['due_date'] = date("Y-m-d", strtotime($request->due_date));
                $job->update([
                    'job_description' => $request->job_description,
                    'customer_id' => $request->customer_id,
                    'system_id' => $request->system_id,
                    'location_id' => $request->location_id,
                    'division_id' => $request->division_id,
                    'employee_id' => $request->employee_id,
                    'jobCoordinator_id' => $request->jobCoordinator_id,
                    'requestedEmployee_id' => $request->requestedEmployee_id,
                    'remarks' => $request->remarks,
                    'priority' => $request->priority,
                    'due_date' => $newJob['due_date'],
                ]);
                $response = Job::with('division', 'customer', 'employee', 'jobPriority')->find($request->id);
                return response()->json(['message' => "Job Updated", 'job' => $response], 201);
            } else {
                return response()->json(['message' => "Can't find the job " . $request->job_no], 400);
            }
        } catch (Exception $e) {
            return response()->json(['error' => $e], 500);
        }
    }

    public function getJobsWithTasks($user)
    {
        $userObj = User::find($user);
        $jobs = $userObj->pendingJobs;
        foreach ($jobs as $key => $job) {
            $tasks = DB::table('tasks')->join('task_user', 'tasks.id', '=', 'task_user.task_id')
                ->where('job_user_id', $job->pivot->id)
                ->Where('plan_date', null)
                ->orderBy('task_no', 'asc')->get();
            $jobs[$key]->tasks = $tasks ? $tasks : [];
        }

        // foreach ($jobs as $key1 => $job) {
        //     $tasks=[];
        //     foreach ($job->pendingTasks as $key2 => $task) {
        //         $taskRes = DB::table('tasks')->join('task_user', 'tasks.id', '=', 'task_user.task_id')->where('tasks.id', $task->id)->Where('user_id',$user)->Where('plan_date',null)->first();
        //         if($taskRes){
        //             $jobs[$key1]->pendingTasks[$key2] = $taskRes;
        //         }
        //     }
        // }
        return $jobs;
    }

    public function getJobById($job_id)
    {
        return Job::find($job_id);
    }

    public function changeJobDueDate(Request $request)
    {
        $user_id = auth()->user()->id;
        $due_date = $request->due_date;
        $job_id = $request->job_id;
        $reason = $request->reason;

        DB::beginTransaction();

        try {
            $selected_job = Job::find($job_id);

            $current_date = Carbon::parse(date('Y-m-d', strtotime(now())));
            $current_due_date = Carbon::parse(date('Y-m-d', strtotime($selected_job->due_date)));
            $new_due_date = Carbon::parse(date('Y-m-d', strtotime($due_date)));

            if ($current_date->gt($new_due_date)) {
                return response()->json(['message' => 'Due date should be greater than or equal to current date'], 422);
            }

            $selected_job->update(['due_date' => date('Y-m-d', strtotime($due_date))]);
            JobDueDateChange::create([
                'job_id' => $job_id,
                'changed_by' => $user_id,
                'due_date' => $new_due_date,
                'reason' => $reason,
            ]);
            DB::commit();
            return response()->json(['message' => 'success']);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
    }

    public function pendingApprovals()
    {
        $pending = AuthorizationRequest::select('authorization_requests.task_user_id', 'authorization_requests.job_user_id', 'tasks.id AS task_id', 'tasks.task_no', 'tasks.Time02', 'tasks.task_description', 'jobs.job_no')->where('authorization_requests.status', 15)
            ->join('task_user', 'authorization_requests.task_user_id', '=', 'task_user.id')
            ->join('tasks', 'task_user.task_id', '=', 'tasks.id')
            ->join('jobs', 'authorization_requests.job_id', '=', 'jobs.id')
            ->get();

        return $pending;
    }
}
