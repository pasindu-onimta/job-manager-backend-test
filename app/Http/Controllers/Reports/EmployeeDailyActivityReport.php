<?php

namespace App\Http\Controllers\Reports;

use App\Activity;
use App\Http\Controllers\Controller;
use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;
use App\User;
use App\EmployeeActivity;
use Exception;
use Illuminate\Support\Facades\Http;

class EmployeeDailyActivityReport extends Controller
{
  public function index(Request $request)
  {
    $user_id = $request->payload['user_id'];
    $startDate = date("Y-m-d", strtotime($request->payload['range']['start']));
    $endDate = date("Y-m-d", strtotime($request->payload['range']['end']));
    $current_date = date("Y-m-d");
    $original_report = [];
    $global_months = 0;
    $global_days = 0;
    $global_hours = 0;
    $global_minutes = 0;
    try {

      $report = DB::select("SELECT 
        `tasks`.`id` AS task_id,
        `tasks`.`task_no`,
        `activities`.`section_id`,
        `activities`.`task_user_id`,
        `tasks`.`task_description`,
        `activities`.`created_at`,
        `activities`.`updated_at`,
        `activities`.`user_id`,
        `jobs`.`customer_name`,
        `jobs`.`job_no`,
        `jobs`.`system`,
        `jobs`.`customer_id`,
        `users`.`name`,
        `employees`.`emp_code`,
        (SELECT `name` FROM `divisions` WHERE id=`users`.`division_id`) AS division
        FROM `activities` 
        LEFT JOIN `tasks` ON `activities`.`task_id` = `tasks`.`id`
        INNER JOIN `jobs` ON `jobs`.`id` = `tasks`.`job_id`
        INNER JOIN `sections` ON `tasks`.`current_status` = `sections`.`id`
        INNER JOIN `users` ON `activities`.`user_id` = `users`.`id`
        INNER JOIN `employees` ON `employees`.`id` = `users`.`employee_id`
        WHERE `activities`.`user_id` = $user_id AND `activities`.`section_id` = 3
        AND CONVERT(`activities`.`created_at`, DATE) BETWEEN CONVERT('$startDate', DATE)
        AND CONVERT('$endDate', DATE) 
        ORDER BY `activities`.`created_at` DESC");

      $other_activities = DB::select("SELECT 
            `tasks`.`id` AS task_id,
            '' AS task_no,
            `comments`.`comment` AS task_description,
            `comments`.`created_at`,
            `comments`.`updated_at`,
            `comments`.`user_id`,
            '' AS customer_name,
            '' AS job_no,
            '' AS system,
            '' AS customer_id,
            `users`.`name`,
            `employees`.`emp_code`,
            (SELECT `name` FROM `divisions` WHERE id=`users`.`division_id`) AS division
      FROM comments
      INNER JOIN `users` ON `comments`.`user_id` = `users`.`id`
      INNER JOIN `employees` ON `employees`.`id` = `users`.`employee_id`
      INNER JOIN `activities` ON `comments`.`activity_id` = `activities`.`id`
      INNER JOIN `tasks` ON `activities`.`task_id` = `tasks`.`id`
      WHERE `comments`.`user_id` = $user_id AND `comments`.`comment_type` IN (6, 10)
      AND CONVERT(`comments`.`created_at`, DATE) BETWEEN CONVERT('$startDate', DATE)
      AND CONVERT('$endDate', DATE) 
      ORDER BY `comments`.`created_at` DESC");


      foreach ($other_activities as $key => $activity) {
        array_push($report, $activity);
      }

      $collection = collect($report);

      $sorted = $collection->sortByDesc('created_at')->values()->all();
      // $sorted = $collection->sortByDesc('price');

      // $sorted->values()->all();

      // return $sorted;
      foreach ($sorted as $key => $value) {
        $months_3 = 0;
        $days_3 = 0;
        $hours_3 = 0;
        $minutes_3 = 0;

        $sdate_3 = Carbon::parse($value->created_at);
        $edate_3 = Carbon::parse($value->updated_at);
        $diff = $sdate_3->diff($edate_3);
        $months_3 += $diff->m;
        $days_3 += $diff->d;
        $hours_3 += $diff->h;
        $minutes_3 += $diff->i;

        if ($value->created_at == $value->updated_at) {
          $sdate2_3 = Carbon::parse($value->created_at);
          $edate2_3 = Carbon::parse(now());
          $diff2 = $sdate2_3->diff($edate2_3);
          $months_3 += $diff2->m;
          $days_3 += $diff2->d;
          $hours_3 += $diff2->h;
          $minutes_3 += $diff2->i;
        }

        $activity_spent = $this->getSpentTime($months_3, $days_3, $hours_3, $minutes_3);
        $value->total_duration = $activity_spent;

        // $created_at = Carbon::parse(date("Y-m-d H:i", strtotime($value->created_at)));
        // $updated_at = Carbon::parse(date("Y-m-d H:i", strtotime($value->updated_at)));

        // if (!$created_at->eq($updated_at)) {
        //   array_push($original_report, $value);
        // }

        if ($activity_spent['minutes'] != '00' || $activity_spent['hours'] != '00') {
          array_push($original_report, $value);
        }
      }

      $comment_types = [6, 7, 8, 9, 10];
      $spent_types = [];

      foreach ($comment_types as $key => $ctype) {
        $all_spents = DB::table('comments')
          ->select('comments.comment', 'comments.comment_type', 'comments.updated_at', 'comments.created_at')
          ->where(['user_id' => $user_id, 'comment_type' => $ctype])
          ->whereRaw("CONVERT(comments.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)")
          ->get();

        $months = 0;
        $days = 0;
        $hours = 0;
        $minutes = 0;
        foreach ($all_spents as $key => $spent) {
          $sdate = Carbon::parse($spent->created_at);
          $edate = Carbon::parse($spent->updated_at);
          $diff = $sdate->diff($edate);
          $months += $diff->m;
          $days += $diff->d;
          $hours += $diff->h;
          $minutes += $diff->i;

          if ($spent->created_at == $spent->updated_at) {
            $sdate2 = Carbon::parse($spent->created_at);
            $edate2 = Carbon::parse(now());
            $diff2 = $sdate2->diff($edate2);
            $months += $diff2->m;
            $days += $diff2->d;
            $hours += $diff2->h;
            $minutes += $diff2->i;
          }
        }

        if ($ctype == 10) {
          $global_months = $months;
          $global_days = $days;
          $global_hours = $hours;
          $global_minutes = $minutes;
        } else {
          $sp = $this->getSpentTime($months, $days, $hours, $minutes);
          array_push($spent_types, [
            'spent' => $sp,
            'type' => $ctype,
          ]);
        }
      }

      $all_ongoings = DB::table('activities')
        ->whereRaw("CONVERT(activities.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)")
        ->where(['user_id' => $user_id, 'section_id' => 3])
        ->get();

      // $ongoing_months = 0;
      // $ongoing_days = 0;
      // $ongoing_hours = 0;
      // $ongoing_minutes = 0;
      foreach ($all_ongoings as $key => $ongoing) {
        $sdate = Carbon::parse($ongoing->created_at);
        $edate = Carbon::parse($ongoing->updated_at);
        $diff = $sdate->diff($edate);
        $global_months += $diff->m;
        $global_days += $diff->d;
        $global_hours += $diff->h;
        $global_minutes += $diff->i;
      }

      if ($endDate == $current_date) {
        $latest_ongoing = Activity::whereDate('created_at', $endDate)->where(['user_id' => $user_id, 'section_id' => 3])->orderBy('created_at', 'DESC')->first();
        if ($latest_ongoing) {
          $checking = Activity::where(['task_user_id' => $latest_ongoing->task_user_id])->orderBy('created_at', 'DESC')->first();
          if ($checking && $checking->section_id == 3) {
            $sdate2 = Carbon::parse($checking->created_at);
            $edate2 = Carbon::parse(now());
            $diff2 = $sdate2->diff($edate2);
            $global_months += $diff2->m;
            $global_days += $diff2->d;
            $global_hours += $diff2->h;
            $global_minutes += $diff2->i;
          }
        }
      }



      $ongoing_spent = $this->getSpentTime($global_months, $global_days, $global_hours, $global_minutes);
      array_push($spent_types, [
        'spent' => $ongoing_spent,
        'type' => 1,
      ]);

      return response()->json(['report' => $original_report, 'all_spents' => $spent_types]);
    } catch (\Throwable $th) {
      return $th;
    }
  }

  public function getSpentTime($months, $days, $hours, $minutes)
  {
    if ($minutes >= 60) {
      $hours += floor($minutes / 60);
      $minutes = $minutes % 60;
    }
    return ['months' => $months, 'days' => sprintf("%02d", $days), 'hours' => sprintf("%02d", $hours), 'minutes' => sprintf("%02d", $minutes)];
  }

  public function all_emp_daily_activity(Request $request)
  {
    $startDate = date("Y-m-d", strtotime($request->payload['range']['start']));
    $endDate = date("Y-m-d", strtotime($request->payload['range']['end']));
    $division = $request->payload['division'];
    $comment_types = [6, 7, 8, 9, 10];
    $all_report = [];
    $current_date = date("Y-m-d");
    if ($division == 0) {
      $users = User::where(['status' => 1, 'user_type' => 4])->get();
    } else {
      $users = User::where(['status' => 1, 'division_id' => $division, 'user_type' => 4])->get();
    }
    $global_months = 0;
    $global_days = 0;
    $global_hours = 0;
    $global_minutes = 0;

    DB::beginTransaction();

    try {
      foreach ($users as $key => $user) {
        $spent_types = [];
        foreach ($comment_types as $key => $ctype) {
          $all_spents = DB::table('comments')
            ->select('comments.comment', 'comments.comment_type', 'comments.updated_at', 'comments.created_at')
            ->where(['user_id' => $user->id, 'comment_type' => $ctype])
            ->whereRaw("CONVERT(comments.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)")
            ->get();

          $months = 0;
          $days = 0;
          $hours = 0;
          $minutes = 0;
          foreach ($all_spents as $key => $spent) {
            $sdate = Carbon::parse($spent->created_at);
            $edate = Carbon::parse($spent->updated_at);
            $diff = $sdate->diff($edate);
            $months += $diff->m;
            $days += $diff->d;
            $hours += $diff->h;
            $minutes += $diff->i;

            if ($spent->created_at == $spent->updated_at) {
              $sdate2 = Carbon::parse($spent->created_at);
              $edate2 = Carbon::parse(now());
              $diff2 = $sdate2->diff($edate2);
              $months += $diff2->m;
              $days += $diff2->d;
              $hours += $diff2->h;
              $minutes += $diff2->i;
            }
          }
          if ($ctype == 10) {
            $global_months = $months;
            $global_days = $days;
            $global_hours = $hours;
            $global_minutes = $minutes;
          } else {
            $sp = $this->getSpentTime($months, $days, $hours, $minutes);
            array_push($spent_types, [
              'spent' => $sp,
              'type' => $ctype,
            ]);
          }
        }

        $all_ongoings = DB::table('activities')
          ->whereRaw("CONVERT(activities.created_at, DATE) BETWEEN CONVERT('$startDate', DATE) AND CONVERT('$endDate', DATE)")
          ->where(['user_id' => $user->id, 'section_id' => 3])
          ->get();

        // $ongoing_months = 0;
        // $ongoing_days = 0;
        // $ongoing_hours = 0;
        // $ongoing_minutes = 0;
        foreach ($all_ongoings as $key => $ongoing) {
          $sdate = Carbon::parse($ongoing->created_at);
          $edate = Carbon::parse($ongoing->updated_at);
          $diff = $sdate->diff($edate);
          $global_months += $diff->m;
          $global_days += $diff->d;
          $global_hours += $diff->h;
          $global_minutes += $diff->i;
        }

        if ($endDate == $current_date) {
          $latest_ongoing = Activity::whereDate('created_at', $endDate)->where(['user_id' => $user->id, 'section_id' => 3])->orderBy('created_at', 'DESC')->first();
          if ($latest_ongoing) {
            $checking = Activity::where(['task_user_id' => $latest_ongoing->task_user_id])->orderBy('created_at', 'DESC')->first();
            if ($checking && $checking->section_id == 3) {
              $sdate2 = Carbon::parse($checking->created_at);
              $edate2 = Carbon::parse(now());
              $diff2 = $sdate2->diff($edate2);
              $global_months += $diff2->m;
              $global_days += $diff2->d;
              $global_hours += $diff2->h;
              $global_minutes += $diff2->i;
            }
          }
        }

        $ongoing_spent = $this->getSpentTime($global_months, $global_days, $global_hours, $global_minutes);
        array_push($spent_types, [
          'spent' => $ongoing_spent,
          'type' => 1,
        ]);

        $userName = $user->name;

        $startDate_2 = Carbon::parse($startDate);
        $endDate_2 = Carbon::parse($endDate);
        $current_date_2 =  Carbon::parse($current_date);

        if ($startDate_2->eq($endDate_2) && !$startDate_2->gt($current_date_2)) {
          $hasPlannedLeaves = DB::table('activity_plans')->where(['user_id' => $user->id, 'title' => 'Leave'])
            ->whereRaw("DATE('$startDate_2') BETWEEN DATE(START) AND (END)")->count();

          $hasActivities = DB::table('activities')->where(['user_id' => $user->id])
            ->whereRaw("DATE(created_at) = DATE('$startDate_2')")->count();

          if (($hasPlannedLeaves == 1 && $hasActivities == 0) || ($hasPlannedLeaves == 0 && $hasActivities == 0)) {
            $userName = $userName . " (Off)";
          } else {
            $hasLeave = DB::select("SELECT MAX(created_at) AS created_at, MAX(updated_at) AS updated_at 
                FROM comments 
                WHERE user_id=2 
                AND comment_type=11 
                AND DATE(created_at) = DATE('$startDate_2')
                AND CONVERT(created_at, DATETIME) = CONVERT(updated_at, DATETIME)
                GROUP BY `comment_type`");

            if (count($hasLeave)) {
              $userName = $userName . " (Leave)";
            }
          }
        }

        $emp = [
          'employee_id' => $user->id,
          'employee_name' => $userName,
        ];

        foreach ($spent_types as $key => $st) {
          switch ($st['type']) {
            case 1:
              $emp['Worked'] = $this->calculateSpentTime($st['type'], $st['spent']);
              break;
            case 6:
              $emp['Meeting'] = $this->calculateSpentTime($st['type'], $st['spent']);
              break;
            case 7:
              $emp['Lunch'] = $this->calculateSpentTime($st['type'], $st['spent']);
              break;
            case 8:
              $emp['Other'] = $this->calculateSpentTime($st['type'], $st['spent']);
              break;

            default:
              # code...
              break;
          }
        }

        array_push($all_report, $emp);
      }

      foreach ($all_report as $key => $rep) {
        EmployeeActivity::create([
          'employee_id' => $rep['employee_id'],
          'worked' => $rep['Worked'],
          'meeting' => $rep['Meeting'],
          'lunch' => $rep['Lunch'],
          'other' => $rep['Other'],
          'batch' => 1,
        ]);
      }
      DB::commit();
      return response()->json($all_report);
    } catch (Exception $e) {
      DB::rollback();
      return response()->json(['error' => $e]);
    }
  }

  public function calculateSpentTime($type, $spent)
  {
    $ongoingSpent = "0 hrs 0 mins";
    $meetingSpent = "0 hrs 0 mins";
    $lunchSpent = "0 hrs 0 mins";
    $holdSpent = "0 hrs 0 mins";

    $days = (int) $spent['days'] * 24;
    $hours = (int) $spent['hours'];
    $minutes = (int) $spent['minutes'];

    if ($minutes >= 60) {
      $hours += floor($minutes / 60);
      $minutes = floor($minutes % 60);
    }

    $spent_final = '';
    $spent_final = $hours + $days . ' hrs ' . $minutes . ' mins';
    switch ($type) {
      case 1:
        $ongoingSpent = $spent_final;
        break;
      case 6:
        $meetingSpent = $spent_final;
        break;
      case 7:
        $lunchSpent = $spent_final;
        break;
      case 8:
        $holdSpent = $spent_final;
        break;
    }
    return $spent_final;
  }

  public function isEmployeeDayEnd(Request $request)
  {
    $user_id = auth()->user()->id;
    $date = date('Y-m-d', strtotime($request->date));
    $result = DB::select("SELECT * FROM comments WHERE comment_type=9 AND user_id =$user_id AND DATE(`created_at`) = DATE('$date')
    AND worksheet_updated=0");
    if (!empty($result)) {
      return 'true';
    } else {
      return 'false';
    }
  }

  public function uploadToWorksheet(Request $request)
  {
    $parameters = [];
    $date = $request->post_date;
    $user_name = explode(' ', auth()->user()->name)[0];

    foreach ($request->data as $key => $value) {
      $job_no = $value['job_no'] != '' ? $value['job_no'] : 'Meeting';

      $job_type = '';

      if ($value['job_no'] == '') {
        $job_type = "Meeting";
      }

      $a = explode('-', $value['job_no'])[0];

      switch ($a) {
        case 'TS':
          $job_no = 'Other';
          $job_type = 'Internal Office Works';
          break;
        case 'QJ':
          $job_no = 'Other';
          $job_type = 'Internal Office Works';
          break;
        case 'OW':
          $job_no = 'Other';
          $job_type = 'Internal Office Works';
          break;
      }

      array_push($parameters, [
        [
          'Para_Name' => '@Post_Date',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '10',
          'Para_Direction' => 'Input',
          'Para_Data' => $date,
        ],
        [
          'Para_Name' => '@Emp_Name',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '50',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['employee_name'],
        ],
        [
          'Para_Name' => '@Emp_Code',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '10',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['employee_code'],
        ],
        [
          'Para_Name' => '@Project',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '50',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['system'] != '' ? $value['system'] : '',
        ],
        [
          'Para_Name' => '@Customer',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '50',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['customer_name'] != '' ? $value['customer_name'] : 'Other',
        ],
        [
          'Para_Name' => '@Time_In',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '20',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['time_in'],
        ],
        [
          'Para_Name' => '@Time_Out',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '20',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['time_out'],
        ],
        [
          'Para_Name' => '@JobNo',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '20',
          'Para_Direction' => 'Input',
          'Para_Data' => $job_no,
        ],
        [
          'Para_Name' => '@Description',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '1000',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['task_description'],
        ],
        [
          'Para_Name' => '@CusCode',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '10',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['customer_id'] != '' ? $value['customer_id'] : 'Other',
        ],
        [
          'Para_Name' => '@LoggedUser',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '15',
          'Para_Direction' => 'Input',
          'Para_Data' => $user_name,
        ],
        [
          'Para_Name' => '@Division',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '6',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['division'],
        ],
        [
          'Para_Name' => '@TaskNo',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '20',
          'Para_Direction' => 'Input',
          'Para_Data' => $value['task_no'],
        ],
        [
          'Para_Name' => '@TaskState',
          'Para_Type' => 'Bit',
          'Para_Lenth' => 0,
          'Para_Direction' => 'Input',
          'Para_Data' => 0,
        ],
        [
          'Para_Name' => '@IsTodayAllocatedTask',
          'Para_Type' => 'Bit',
          'Para_Lenth' => 0,
          'Para_Direction' => 'Input',
          'Para_Data' => 1,
        ],
        [
          'Para_Name' => '@JobType',
          'Para_Type' => 'Varchar',
          'Para_Lenth' => '100',
          'Para_Direction' => 'Input',
          'Para_Data' => $job_type,
        ],

      ]);
    }

    $abc = [];

    // return $parameters;

    foreach ($parameters as $key => $value) {
      array_push($abc, $key);

      $task_sheet = Http::post('http://onimtait.dyndns.info:9000/api/AndroidApi/CommonExecute', [
        // $task_sheet =  Http::post('http://192.168.1.60:9000/api/AndroidApi/CommonExecute', [
        'SpName' => 'WEB_sp_DailyWorkSheetFileUpdate',
        'HasReturnData' => 'F',
        'Parameters' => $value
      ])->json();
    }

    $ss = DB::table('comments')->where(['user_id' => auth()->user()->id, 'comment_type' => 9])
      ->whereDate('created_at', date('Y-m-d', strtotime(str_replace('/', '-', $date))))
      ->orderBy('created_at', 'DESC')
      ->update(['worksheet_updated' => 1]);

    return $task_sheet;
  }
}
