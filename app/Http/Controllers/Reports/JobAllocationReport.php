<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Task;

class JobAllocationReport extends Controller
{
    public function index(Request $request)
    {
        // DB::enableQueryLog();
        $startDate = date("Y-m-d", strtotime($request->payload['range']['start']));
        $endDate = date("Y-m-d", strtotime($request->payload['range']['end']));
        $filter = $request->payload['filter_1'];
        $filter_2 = $request->payload['filter_2'];
        $department = $request->payload['department'];
        $department_code = '0';
        switch ($department) {
            case '1':
                $department_code = "S";
                break;
            case '2':
                $department_code = "T";
                break;
            case '3':
                $department_code = "C";
                break;
        }
        // return $department_code;
        if ($filter == 2) {
            
            $query = DB::table('jobs')
                ->select('jobs.id', 'jobs.job_no', 'jobs.job_description', 'jobs.customer_name', 'jobs.customer_id', 'jobs.system', DB::raw('IFNULL(users.name, "N/A") AS name'), 'jobs.due_date')
                ->join('job_user', 'jobs.id', '=', 'job_user.job_id')
                ->join('users', 'job_user.user_id', '=', 'users.id')
                ->whereRaw("CONVERT(jobs.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)");
                if ($department != '0') {
                    $query->where("users.division_id", $department);
                }
                $result = $query->groupBy('job_user.job_id')
                ->orderBy('users.name')
                ->get();
        } 
        else if ($filter == 3) {
            $query = DB::table('jobs')
                ->select('jobs.id', 'jobs.job_no', 'jobs.job_description', 'jobs.customer_name', 'jobs.customer_id', 'jobs.system', DB::raw('IFNULL(users.name, "N/A") AS name'), 'jobs.due_date')
                ->leftJoin('job_user', 'jobs.id', '=', 'job_user.job_id')
                ->leftJoin('users', 'job_user.user_id', '=', 'users.id')
                ->whereRaw("CONVERT(jobs.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)")
                ->whereRaw("jobs.id NOT IN(SELECT job_id FROM job_user)");
                if ($department_code != '0') {
                    $query->whereRaw("(jobs.job_no LIKE '$department_code%' OR users.division_id LIKE '$department%')");
                }
                $result = $query->orderBy('users.name')
                ->get();
        } 
        else {
            $query = DB::table('jobs')
                ->select('jobs.id', 'jobs.job_no', 'jobs.job_description', 'jobs.customer_name', 'jobs.customer_id', 'jobs.system', DB::raw('IFNULL(users.name, "N/A") AS name'), 'jobs.due_date')
                ->leftJoin('job_user', 'jobs.id', '=', 'job_user.job_id')
                ->leftJoin('users', 'job_user.user_id', '=', 'users.id')
                ->whereRaw("CONVERT(jobs.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)");
                if ($department_code != '0') {
                    $query->whereRaw("(jobs.job_no LIKE '$department_code%' OR users.division_id LIKE '$department%')");
                }
                $result = $query->groupBy('jobs.job_no')->orderBy('users.name')
                ->get();
        }
        // return DB::getQueryLog();
        

            foreach ($result as $key => $value) {
                $task_count = DB::table('tasks')->where(['job_id' => $value->id])->count();
                $value->task_count = $task_count;
            }

        return response()->json(['data' => $result], 200);
    }

    public function tasks(Request $request)
    {
        $job_id = $request->payload['job_id'];
        $tasks = Task::where('job_id', $job_id)->get();
        return response()->json(['data' => $tasks], 200);
    }
}
