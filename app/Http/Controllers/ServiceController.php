<?php

namespace App\Http\Controllers;

use App\ServiceDetails;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $startDate = date("Y-m-d", strtotime($request['start']));
        $endDate = date("Y-m-d", strtotime($request['end']));

        $service_details = DB::select("SELECT 
                `customers`.`customer_name`,
                `branches`.`branch_name`,
                `branches`.`agreement_number`,
                `service_details`.`job_card_no`,
                `users`.`name`,
                `service_details`.`pos_count`,
                `service_details`.`server_count`,
                `service_details`.`terminal_count`,
                `service_details`.`service_date`,
                `service_details`.`created_at`
                FROM `service_details`
                INNER JOIN `customers` ON `service_details`.`customer_id` = `customers`.`id`
                INNER JOIN `branches` ON `customers`.`id` = `branches`.`customer_id`
                INNER JOIN `users` ON `service_details`.`user_id` = `users`.`id`
                WHERE `service_details`.`status` = 1
                AND CONVERT(`service_details`.`created_at`, DATE) BETWEEN CONVERT('$startDate', DATE)
                AND CONVERT('$endDate', DATE) 
                ORDER BY `service_details`.`created_at` DESC");

        return response()->json($service_details, 200);
    }

    public function store(Request $request)
    {
        $employees = $request->employees;
        $logged_user = auth()->user()->id;

        foreach ($employees as $key => $employee) {
            ServiceDetails::create([
                'customer_id' => $request->selected_customer,
                'branch_id' => $request->selected_branch,
                'agreement_number' => $request->selected_agreement,
                'job_card_no' => $request->job_card_no,
                'pos_count' => $request->pos_count,
                'server_count' => $request->server_count,
                'terminal_count' => $request->terminal_count,
                'service_date' => date("Y-m-d", strtotime($request->service_date)),
                'user_id' => $employee['id'],
                'created_by' => $logged_user,
                'status' => 1,
            ]);
        }

        return response()->json(['status' => 'Success'], 201);
    }
}
