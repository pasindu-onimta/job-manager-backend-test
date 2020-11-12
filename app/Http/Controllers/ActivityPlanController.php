<?php

namespace App\Http\Controllers;

use App\ActivityPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivityPlanController extends Controller
{
    public function store(Request $request)
    {
        $activity = $request->all();
        $activity['start'] = date('Y-m-d H:i:s', strtotime($request->start));
        $activity['end'] = date('Y-m-d H:i:s', strtotime(Carbon::parse(date('Y-m-d H:i:s', strtotime($request->end)))->subSecond()));
        return ActivityPlan::create($activity);
    }

    public function getUserPlan($user_id)
    {
        return ActivityPlan::where('user_id', $user_id)->where('deleted_at', null)->get();
    }

    public function delete($id)
    {
        $activity = ActivityPlan::find($id);
        return $activity->delete();
    }
}
