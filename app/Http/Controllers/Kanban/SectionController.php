<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Section;
use App\Job;
use App\User;
use App\Jobtype;

class SectionController extends Controller
{
    public function index(Request $request)
    {

        $logged_user_jobs = [];
        $job_type = $request->type;
        $user_id = auth()->user()->id;
  
        $jobs = User::find($user_id)->jobs()->orderBy('due_date', 'ASC')->get();
        $sections = Jobtype::where('id', $job_type)->first()->sections;
        
        foreach ($sections as $key => $section) {
            array_push($logged_user_jobs, $section);
        }
        return $logged_user_jobs;
    }
}
