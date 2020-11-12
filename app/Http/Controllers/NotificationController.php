<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notification;
use App\User;
use App\Employee;

class NotificationController extends Controller
{
    public function index()
    {
        $new_notifications = auth()->user()->notifications()->orderBy('created_at', 'DESC')->limit(5)->get()->load('user', 'job');
        $new_notifications_count = auth()->user()->notifications()->where('status', 1)->count();
        $data = [];
        foreach ($new_notifications as $key => $notification) {
            $emp_id = User::find($notification->assigend_by)->employee_id;
            $notification->assigend_by = User::find($notification->assigend_by);
            $notification->emp_details = Employee::find($emp_id);
            array_push($data, $notification);
        }
        // return $new_notifications;
        return response()->json(['notifications' => $new_notifications, 'notifications_count' => $new_notifications_count]);
    }
}
