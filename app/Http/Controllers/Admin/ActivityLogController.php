<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $log = DB::select("SELECT 
        activity_log.id,
        activity_log.log_name,
        users.name,
        activity_log.description,
        activity_log.properties,
        activity_log.created_at
        FROM activity_log
        INNER JOIN users ON activity_log.causer_id = users.`id`");

        return $log;
    }
}
